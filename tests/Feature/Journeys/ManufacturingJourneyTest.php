<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Manufacturing journey test.
 *
 * Verifies the complete Work Order lifecycle from BOM activation through
 * production recording and GL posting on completion.
 *
 * Scenarios:
 *   1. Full work order lifecycle: create → start → record production → complete
 *   2. GL journal posted on completion when FG/WIP accounts are configured
 *   3. Draft BOM is rejected when creating a work order
 *   4. Completed work order cannot be started again
 *   5. Work order organization_id is always the authenticated user's org
 */
class ManufacturingJourneyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'manufacturing.workorders.view',
            'manufacturing.workorders.create',
            'manufacturing.workorders.edit',
            'manufacturing.workorders.start',
            'manufacturing.workorders.produce',
            'manufacturing.workorders.complete',
            'manufacturing.workorders.cancel',
            'manufacturing.workorders.release',
        ]);
        $this->setUpOpenFiscalPeriod();
    }

    // =========================================================================
    // 1. Full work order lifecycle
    // =========================================================================

    public function test_full_work_order_lifecycle(): void
    {
        $bom = $this->createActiveBom();

        // Create work order → STATUS_DRAFT
        $createResponse = $this->apiPost('/manufacturing/work-orders', [
            'bom_template_id'    => $bom->id,
            'planned_quantity'   => 10,
            'planned_start_date' => now()->format('Y-m-d'),
            'planned_end_date'   => now()->addDays(5)->format('Y-m-d'),
        ]);
        $createResponse->assertStatus(201);

        $workOrderId = $createResponse->json('data.id');
        $this->assertNotNull($workOrderId);

        $workOrder = WorkOrder::find($workOrderId);
        $this->assertEquals(WorkOrder::STATUS_DRAFT, $workOrder->status);
        $this->assertEquals($this->organization->id, $workOrder->organization_id);
        $this->assertEquals($bom->product_id, $workOrder->product_id);

        // Release work order → STATUS_RELEASED
        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/release")
            ->assertStatus(200);

        // Start work order → STATUS_IN_PROGRESS
        $startResponse = $this->apiPost("/manufacturing/work-orders/{$workOrderId}/start");
        $startResponse->assertStatus(200);

        $workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $workOrder->status);
        $this->assertNotNull($workOrder->actual_start_datetime);

        // Record production output
        $prodResponse = $this->apiPost("/manufacturing/work-orders/{$workOrderId}/record-production", [
            'good_quantity'     => 8,
            'rejected_quantity' => 2,
            'batch_number'      => 'BATCH-TEST-001',
            'notes'             => 'Journey test production run',
        ]);
        $prodResponse->assertStatus(200);

        $workOrder->refresh();
        $this->assertGreaterThan(0, (float) $workOrder->produced_quantity);

        // Complete work order → STATUS_COMPLETED
        $completeResponse = $this->apiPost("/manufacturing/work-orders/{$workOrderId}/complete");
        $completeResponse->assertStatus(200);

        $workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $workOrder->status);
        $this->assertEquals($this->organization->id, $workOrder->organization_id);
        $this->assertDatabaseHas('work_orders', [
            'id'     => $workOrderId,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
    }

    // =========================================================================
    // 2. GL journal is posted on completion when accounts are configured
    // =========================================================================

    public function test_work_order_completion_posts_gl_when_accounts_configured(): void
    {
        [$fgAccount, $wipAccount] = $this->seedManufacturingGlAccounts();

        Config::set('erp.default_accounts.fg_inventory', $fgAccount->id);
        Config::set('erp.default_accounts.wip_inventory', $wipAccount->id);

        $bom = $this->createActiveBom(['overhead_cost' => 200.00]);

        // Create and start the work order
        $woResponse = $this->apiPost('/manufacturing/work-orders', [
            'bom_template_id'    => $bom->id,
            'planned_quantity'   => 5,
            'planned_start_date' => now()->format('Y-m-d'),
        ]);
        $woResponse->assertStatus(201);
        $workOrderId = $woResponse->json('data.id');

        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/release")->assertStatus(200);
        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/start")->assertStatus(200);

        // Record full production → produced_quantity = planned_quantity = 5
        // actual_overhead_cost = 200 * (5/5) = 200.00 after completion
        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/record-production", [
            'good_quantity' => 5,
        ])->assertStatus(200);

        // Complete — overhead cost is non-zero, GL must be posted
        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/complete")->assertStatus(200);

        $workOrder = WorkOrder::find($workOrderId);
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $workOrder->status);

        // Verify GL journal entry
        $journalEntry = JournalEntry::where('source_type', WorkOrder::class)
            ->where('source_id', $workOrder->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Journal entry must be created on WO completion when accounts are configured');
        $this->assertEquals($this->organization->id, $journalEntry->organization_id);

        // Double-entry balance
        $journalEntry->load('lines');
        $totalDebit  = (float) $journalEntry->lines->sum('debit');
        $totalCredit = (float) $journalEntry->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.0001, 'GL debits must equal credits');

        // FG account debited, WIP account credited
        $fgLine  = $journalEntry->lines->where('account_id', $fgAccount->id)->first();
        $wipLine = $journalEntry->lines->where('account_id', $wipAccount->id)->first();
        $this->assertNotNull($fgLine, 'FG Inventory account must appear as debit');
        $this->assertNotNull($wipLine, 'WIP Inventory account must appear as credit');
        $this->assertGreaterThan(0, (float) $fgLine->debit);
        $this->assertGreaterThan(0, (float) $wipLine->credit);
    }

    // =========================================================================
    // 3. Draft BOM is rejected
    // =========================================================================

    public function test_draft_bom_cannot_create_work_order(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'type'            => Product::TYPE_SERVICE,
            'is_active'       => true,
            'track_inventory' => false,
        ]);

        $draftBom = BomTemplate::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $product->id,
        ]);

        $response = $this->apiPost('/manufacturing/work-orders', [
            'bom_template_id'    => $draftBom->id,
            'planned_quantity'   => 10,
            'planned_start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // 4. Completed work order cannot be started again
    // =========================================================================

    public function test_completed_work_order_cannot_be_started_again(): void
    {
        $bom = $this->createActiveBom();

        $woResponse = $this->apiPost('/manufacturing/work-orders', [
            'bom_template_id'    => $bom->id,
            'planned_quantity'   => 1,
            'planned_start_date' => now()->format('Y-m-d'),
        ]);
        $woResponse->assertStatus(201);
        $workOrderId = $woResponse->json('data.id');

        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/release")->assertStatus(200);
        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/start")->assertStatus(200);
        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/record-production", [
            'good_quantity' => 1,
        ])->assertStatus(200);
        $this->apiPost("/manufacturing/work-orders/{$workOrderId}/complete")->assertStatus(200);

        // Attempt to start a completed work order — must be rejected
        $response = $this->apiPost("/manufacturing/work-orders/{$workOrderId}/start");
        $response->assertStatus(422);
    }

    // =========================================================================
    // 5. Work order inherits the authenticated user's organization_id
    // =========================================================================

    public function test_work_order_organization_id_matches_authenticated_user(): void
    {
        $bom = $this->createActiveBom();

        $response = $this->apiPost('/manufacturing/work-orders', [
            'bom_template_id'    => $bom->id,
            'planned_quantity'   => 3,
            'planned_start_date' => now()->format('Y-m-d'),
        ]);
        $response->assertStatus(201);

        $workOrderId = $response->json('data.id');
        $workOrder   = WorkOrder::find($workOrderId);

        $this->assertEquals(
            $this->organization->id,
            $workOrder->organization_id,
            'Work order organization_id must match the authenticated user'
        );
        $this->assertNotNull($workOrder->work_order_number);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createActiveBom(array $overrides = []): BomTemplate
    {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'type'            => Product::TYPE_SERVICE,
            'track_inventory' => false,
            'is_active'       => true,
        ]);

        return BomTemplate::factory()->active()->create(array_merge([
            'organization_id' => $this->organization->id,
            'product_id'      => $product->id,
        ], $overrides));
    }

    private function seedManufacturingGlAccounts(): array
    {
        $fg = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'inventory',
            'code'            => '1300',
            'name'            => 'Finished Goods Inventory',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $wip = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'inventory',
            'code'            => '1310',
            'name'            => 'Work In Process Inventory',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        return [$fg, $wip];
    }
}
