<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\InspectionLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Warehouse (Goods Issue) and Quality Management journey test.
 *
 * Scenarios:
 *   1.  GI lifecycle: create DRAFT → post → STATUS_POSTED, GL entry created
 *   2.  GI GL entry carries correct organization_id (never null, never wrong org)
 *   3.  GI reversal: post → reverse → STATUS_REVERSED
 *   4.  Quality Plan creation
 *   5.  Inspection Lot: PENDING → IN_INSPECTION (results recorded) → ACCEPTED
 *   6.  Rejected inspection lot: status → REJECTED
 *   7.  Quality Notification (defect report) carries correct organization_id
 */
class WarehouseQualityJourneyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'inventory.goods-issues.view',
            'inventory.goods-issues.create',
            'inventory.goods-issues.edit',
            'inventory.goods-issues.post',
            'inventory.goods-issues.reverse',
            'manufacturing.quality.view',
            'manufacturing.quality.create',
            'manufacturing.quality.inspect',
            'manufacturing.quality.edit',
        ]);
        $this->setUpOpenFiscalPeriod();

        $unit = UnitOfMeasure::factory()->create(['organization_id' => $this->organization->id]);

        $this->warehouse = Warehouse::factory()->allowNegativeStock()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'type'            => Product::TYPE_SERVICE,
            'track_inventory' => false,   // avoid stock deduction complexity
            'is_active'       => true,
            'unit_id'         => $unit->id,
        ]);
    }

    // =========================================================================
    // 1. Goods Issue lifecycle: create → post
    // =========================================================================

    public function test_goods_issue_create_to_post(): void
    {
        // Create GI in DRAFT
        $createResponse = $this->apiPost('/inventory/goods-issues', [
            'gi_date'       => now()->format('Y-m-d'),
            'movement_type' => GoodsIssue::MOVEMENT_OTHER,
            'warehouse_id'  => $this->warehouse->id,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity'   => 10,
                    'unit_cost'  => 50.00,
                    'notes'      => 'Journey test line',
                ],
            ],
        ]);
        $createResponse->assertStatus(201);

        $giId = $createResponse->json('data.id');
        $this->assertNotNull($giId);

        $gi = GoodsIssue::find($giId);
        $this->assertEquals(GoodsIssue::STATUS_DRAFT, $gi->status);
        $this->assertEquals($this->organization->id, $gi->organization_id);

        // Post the GI → STATUS_POSTED
        $postResponse = $this->apiPost("/inventory/goods-issues/{$giId}/post");
        $postResponse->assertStatus(200);

        $gi->refresh();
        $this->assertEquals(GoodsIssue::STATUS_POSTED, $gi->status);
        $this->assertNotNull($gi->posted_at);
        $this->assertDatabaseHas('goods_issues', [
            'id'     => $giId,
            'status' => GoodsIssue::STATUS_POSTED,
        ]);
    }

    // =========================================================================
    // 2. GI GL entry carries correct organization_id
    // =========================================================================

    public function test_goods_issue_gl_entry_carries_correct_organization_id(): void
    {
        // Seed Inventory and COGS accounts (GI service finds by name pattern)
        $inventoryAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'inventory',
            'code'            => '1200',
            'name'            => 'Raw Material Inventory',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $cogsAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_EXPENSE,
            'sub_type'        => 'cost_of_goods',
            'code'            => '5000',
            'name'            => 'Cost of Goods Sold',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        // Create and post GI with a non-zero unit_cost to trigger GL
        $createResponse = $this->apiPost('/inventory/goods-issues', [
            'gi_date'       => now()->format('Y-m-d'),
            'movement_type' => GoodsIssue::MOVEMENT_SALES_DELIVERY,
            'warehouse_id'  => $this->warehouse->id,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity'   => 5,
                    'unit_cost'  => 100.00,  // total_value = 500 → triggers GL
                ],
            ],
        ]);
        $createResponse->assertStatus(201);
        $giId = $createResponse->json('data.id');

        $this->apiPost("/inventory/goods-issues/{$giId}/post")->assertStatus(200);

        $gi = GoodsIssue::find($giId);
        $this->assertNotNull($gi->journal_entry_id, 'GI must have a journal_entry_id after posting');

        $journalEntry = JournalEntry::find($gi->journal_entry_id);
        $this->assertNotNull($journalEntry);
        $this->assertNotNull($journalEntry->organization_id, 'journal_entry.organization_id must never be null');
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'GL entry must carry the issuing organization_id'
        );

        // Double-entry balance
        $journalEntry->load('lines');
        $totalDebit  = (float) $journalEntry->lines->sum('debit');
        $totalCredit = (float) $journalEntry->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.0001, 'GL must balance');

        // COGS debited, Inventory credited
        $cogsLine  = $journalEntry->lines->where('account_id', $cogsAccount->id)->first();
        $invLine   = $journalEntry->lines->where('account_id', $inventoryAccount->id)->first();
        $this->assertNotNull($cogsLine, 'COGS account must be debited');
        $this->assertNotNull($invLine, 'Inventory account must be credited');
        $this->assertGreaterThan(0, (float) $cogsLine->debit);
        $this->assertGreaterThan(0, (float) $invLine->credit);
    }

    // =========================================================================
    // 3. GI reversal
    // =========================================================================

    public function test_goods_issue_can_be_reversed_after_posting(): void
    {
        $createResponse = $this->apiPost('/inventory/goods-issues', [
            'gi_date'       => now()->format('Y-m-d'),
            'movement_type' => GoodsIssue::MOVEMENT_SCRAPPING,
            'warehouse_id'  => $this->warehouse->id,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity'   => 3,
                    'unit_cost'  => 0,
                ],
            ],
        ]);
        $createResponse->assertStatus(201);
        $giId = $createResponse->json('data.id');

        $this->apiPost("/inventory/goods-issues/{$giId}/post")->assertStatus(200);

        // Reverse the posted GI
        $reverseResponse = $this->apiPost("/inventory/goods-issues/{$giId}/reverse", [
            'reason' => 'Issued in error — reversal test',
        ]);
        $reverseResponse->assertStatus(200);

        $gi = GoodsIssue::find($giId);
        $this->assertEquals(GoodsIssue::STATUS_REVERSED, $gi->status);
        $this->assertDatabaseHas('goods_issues', [
            'id'     => $giId,
            'status' => GoodsIssue::STATUS_REVERSED,
        ]);
    }

    // =========================================================================
    // 4. Quality Plan creation
    // =========================================================================

    public function test_quality_plan_creation(): void
    {
        $response = $this->apiPost('/manufacturing/quality/plans', [
            'name'             => 'Final Inspection Plan',
            'inspection_stage' => 'final',
            'is_active'        => true,
            'description'      => 'Inspect finished goods before dispatch',
            'characteristics'  => [
                [
                    'name'               => 'Dimensional Accuracy',
                    'inspection_method'  => 'Calliper measurement',
                    'measurement_unit'   => 'mm',
                    'lower_limit'        => 9.8,
                    'upper_limit'        => 10.2,
                    'target_value'       => 10.0,
                    'is_mandatory'       => true,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $planId = $response->json('data.id');
        $this->assertNotNull($planId);

        $this->assertDatabaseHas('quality_plans', [
            'id'               => $planId,
            'organization_id'  => $this->organization->id,
            'inspection_stage' => 'final',
        ]);
    }

    // =========================================================================
    // 5. Inspection Lot: PENDING → record results → ACCEPTED
    // =========================================================================

    public function test_inspection_lot_lifecycle_acceptance(): void
    {
        // Create quality plan first
        $planResponse = $this->apiPost('/manufacturing/quality/plans', [
            'name'      => 'Goods Receipt QC Plan',
            'is_active' => true,
        ]);
        $planResponse->assertStatus(201);
        $planId = $planResponse->json('data.id');

        // Create inspection lot (PENDING)
        $lotResponse = $this->apiPost('/manufacturing/quality/inspection-lots', [
            'product_id'      => $this->product->id,
            'quality_plan_id' => $planId,
            'warehouse_id'    => $this->warehouse->id,
            'source_type'     => InspectionLot::SOURCE_MANUAL,
            'quantity'        => 100,
        ]);
        $lotResponse->assertStatus(201);

        $lotId = $lotResponse->json('data.id');
        $this->assertNotNull($lotId);

        $lot = InspectionLot::find($lotId);
        $this->assertEquals(InspectionLot::STATUS_PENDING, $lot->status);
        $this->assertEquals($this->organization->id, $lot->organization_id);

        // Record inspection results
        $resultsResponse = $this->apiPost("/manufacturing/quality/inspection-lots/{$lotId}/results", [
            'results' => [
                [
                    'characteristic_name' => 'Visual Check',
                    'measured_value'      => null,
                    'text_result'         => 'All units pass visual inspection',
                    'is_conforming'       => true,
                ],
            ],
        ]);
        $resultsResponse->assertStatus(200);

        $lot->refresh();
        $this->assertEquals(InspectionLot::STATUS_IN_INSPECTION, $lot->status);

        // Complete inspection — all accepted
        $completeResponse = $this->apiPost("/manufacturing/quality/inspection-lots/{$lotId}/complete", [
            'accepted_quantity' => 100,
            'rejected_quantity' => 0,
        ]);
        $completeResponse->assertStatus(200);

        $lot->refresh();
        $this->assertEquals(InspectionLot::STATUS_ACCEPTED, $lot->status);
        $this->assertEquals(100, (float) $lot->accepted_quantity);
        $this->assertEquals(0, (float) $lot->rejected_quantity);
    }

    // =========================================================================
    // 6. Inspection Lot with rejections → REJECTED status
    // =========================================================================

    public function test_inspection_lot_rejected_when_all_units_fail(): void
    {
        $lotResponse = $this->apiPost('/manufacturing/quality/inspection-lots', [
            'product_id'  => $this->product->id,
            'source_type' => InspectionLot::SOURCE_MANUAL,
            'quantity'    => 50,
        ]);
        $lotResponse->assertStatus(201);
        $lotId = $lotResponse->json('data.id');

        $this->apiPost("/manufacturing/quality/inspection-lots/{$lotId}/results", [
            'results' => [
                [
                    'characteristic_name' => 'Dimensional Check',
                    'is_conforming'       => false,
                    'notes'               => 'All samples out of tolerance',
                ],
            ],
        ])->assertStatus(200);

        $completeResponse = $this->apiPost("/manufacturing/quality/inspection-lots/{$lotId}/complete", [
            'accepted_quantity' => 0,
            'rejected_quantity' => 50,
        ]);
        $completeResponse->assertStatus(200);

        $lot = InspectionLot::find($lotId);
        $this->assertEquals(InspectionLot::STATUS_REJECTED, $lot->status);
        $this->assertEquals(50, (float) $lot->rejected_quantity);
    }

    // =========================================================================
    // 7. Quality Notification carries correct organization_id
    // =========================================================================

    public function test_quality_notification_carries_correct_organization_id(): void
    {
        $response = $this->apiPost('/manufacturing/quality/notifications', [
            'notification_type' => 'defect',
            'source_type'       => 'internal',
            'product_id'        => $this->product->id,
            'title'             => 'Surface defect detected in batch B-2025',
            'description'       => 'Multiple units exhibit surface pitting after final coating.',
            'priority'          => 'high',
            'defects' => [
                [
                    'defect_type'  => 'Surface Pitting',
                    'defect_code'  => 'SP-001',
                    'quantity'     => 15,
                    'severity'     => 'major',
                    'description'  => 'Visible pitting on external surface',
                ],
            ],
        ]);

        $response->assertStatus(201);

        $notificationId = $response->json('data.id');
        $this->assertNotNull($notificationId);

        $this->assertDatabaseHas('quality_notifications', [
            'id'              => $notificationId,
            'organization_id' => $this->organization->id,
        ]);
    }
}
