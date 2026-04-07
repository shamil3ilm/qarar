<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WorkOrderLifecycleTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.workorders.view',
            'manufacturing.workorders.create',
            'manufacturing.workorders.edit',
            'manufacturing.workorders.start',
            'manufacturing.workorders.complete',
            'manufacturing.workorders.cancel',
            'manufacturing.workorders.produce',
        ]);
        $this->setUpOpenFiscalPeriod();
    }

    public function test_can_create_work_order_in_draft_state(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // BOM must be active (STATUS_ACTIVE) — WorkOrderService rejects draft BOMs
        $bom = BomTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $product->id,
        ]);

        $response = $this->apiPost('/manufacturing/work-orders', [
            'bom_template_id'    => $bom->id,
            'product_id'         => $product->id,
            'planned_quantity'   => 100,
            'planned_start_date' => now()->addDays(1)->format('Y-m-d'),
            'planned_end_date'   => now()->addDays(7)->format('Y-m-d'),
            'priority'           => WorkOrder::PRIORITY_NORMAL,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', WorkOrder::STATUS_DRAFT);
    }

    public function test_can_release_draft_work_order(): void
    {
        $bom = BomTemplate::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $workOrder = WorkOrder::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'bom_template_id' => $bom->id,
        ]);

        $response = $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/release");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', WorkOrder::STATUS_RELEASED);
    }

    public function test_can_start_released_work_order(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'status'          => WorkOrder::STATUS_RELEASED,
        ]);

        $response = $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/start");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', WorkOrder::STATUS_IN_PROGRESS);
    }

    public function test_can_complete_in_progress_work_order(): void
    {
        $workOrder = WorkOrder::factory()->inProgress()->create([
            'organization_id'  => $this->organization->id,
            'branch_id'        => $this->branch->id,
            'planned_quantity' => 50,
        ]);

        $response = $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/complete", [
            'produced_quantity' => 50,
            'rejected_quantity' => 0,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', WorkOrder::STATUS_COMPLETED);
    }

    public function test_cannot_start_draft_work_order_skipping_release(): void
    {
        $workOrder = WorkOrder::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
        ]);

        // draft → in_progress is not a valid state transition
        $response = $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/start");

        $response->assertStatus(422);
    }

    public function test_can_cancel_work_order(): void
    {
        $workOrder = WorkOrder::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
        ]);

        $response = $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/cancel", [
            'reason' => 'Production plan changed',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', WorkOrder::STATUS_CANCELLED);
    }
}
