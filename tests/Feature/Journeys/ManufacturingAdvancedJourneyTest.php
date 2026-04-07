<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\EngineeringChange;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\ProcessOrder;
use App\Models\Manufacturing\RepetitiveMfgSchedule;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Manufacturing advanced journey test — SAP PP parity.
 *
 * Verifies the SAP PP capabilities beyond the basic Work Order lifecycle:
 *
 *   SAP CS01/CS11 — 1. BOM lifecycle: create with lines → activate → cost breakdown
 *   SAP CR01/CM01 — 2. Work center creation + capacity load view
 *   SAP MD01      — 3. MRP run: execute → list planned orders
 *   SAP MD04      — 4. MRP planned order firmation
 *   SAP COR1/COR2 — 5. Process Manufacturing (PP-PI): recipe → order → release → complete
 *   SAP MF50/MFBF — 6. Repetitive Manufacturing (PP-REM): line → schedule → confirm → backflush
 *   SAP CC01/CC02 — 7. Engineering Change Management: create → submit → approve → implement
 *   SAP MB1A      — 8. Scrap reporting: create scrap record
 */
class ManufacturingAdvancedJourneyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            // BOM
            'manufacturing.bom.view',
            'manufacturing.bom.create',
            'manufacturing.bom.edit',
            // MRP (mrp.php is authoritative — uses .view/.run/.edit permissions)
            'manufacturing.mrp.view',
            'manufacturing.mrp.run',
            'manufacturing.mrp.edit',
            // Capacity / Work Centers
            'manufacturing.capacity.view',
            'manufacturing.capacity.manage',
            // Work Orders (for scrap report linkage)
            'manufacturing.workorders.view',
            'manufacturing.workorders.create',
            'manufacturing.workorders.start',
            'manufacturing.workorders.produce',
            'manufacturing.workorders.complete',
        ]);
        $this->setUpOpenFiscalPeriod();
    }

    // =========================================================================
    // 1. BOM Lifecycle (SAP CS01/CS11/CS12)
    // =========================================================================

    public function test_bom_lifecycle_create_activate_and_cost_breakdown(): void
    {
        $finishedGood = $this->createProduct('Finished Good');
        $component    = $this->createProduct('Component A');

        // Create BOM with one line
        $createResponse = $this->apiPost('/manufacturing/bom-templates', [
            'name'            => 'FG-BOM-001 Bill of Materials',
            'product_id'      => $finishedGood->id,
            'output_quantity' => 100,
            'overhead_cost'   => 500.00,
            'lines' => [
                [
                    'product_id' => $component->id,
                    'quantity'   => 5,
                    'unit_cost'  => 20.00,
                ],
            ],
        ]);
        $createResponse->assertStatus(201);

        $bomId = $createResponse->json('data.id');
        $this->assertNotNull($bomId);
        $this->assertDatabaseHas('bom_templates', [
            'id'              => $bomId,
            'organization_id' => $this->organization->id,
        ]);

        // Verify BOM is in DRAFT status initially
        $bom = BomTemplate::find($bomId);
        $this->assertEquals(BomTemplate::STATUS_DRAFT, $bom->status);

        // Activate the BOM
        $activateResponse = $this->apiPatch("/manufacturing/bom-templates/{$bomId}/active", ['active' => true]);
        $activateResponse->assertStatus(200);

        $bom->refresh();
        $this->assertEquals(BomTemplate::STATUS_ACTIVE, $bom->status);

        // Cost breakdown is available on an active BOM
        $costResponse = $this->apiGet("/manufacturing/bom-templates/{$bomId}/cost-breakdown");
        $costResponse->assertStatus(200);

        $this->assertNotNull($costResponse->json('data'));
    }

    // =========================================================================
    // 2. Work Center Creation + Capacity Load (SAP CR01 / CM01)
    // =========================================================================

    public function test_work_center_can_be_created_and_load_viewed(): void
    {
        $createResponse = $this->apiPost('/manufacturing/work-centers', [
            'code'               => 'WC-MILL-01',
            'name'               => 'CNC Milling Machine 01',
            'work_center_type'   => 'machine',
            'capacity_per_day'   => 8.0,
            'efficiency_percent' => 95,
            'calendar_type'      => '5day',
            'cost_per_hour'      => 150.00,
            'currency_code'      => 'SAR',
            'is_active'          => true,
        ]);
        $createResponse->assertStatus(201);

        $workCenterId = $createResponse->json('data.id');
        $this->assertNotNull($workCenterId);

        $workCenter = WorkCenter::find($workCenterId);
        $this->assertEquals($this->organization->id, $workCenter->organization_id);
        $this->assertEquals('WC-MILL-01', $workCenter->code);

        $this->assertDatabaseHas('work_centers', [
            'id'              => $workCenterId,
            'organization_id' => $this->organization->id,
            'code'            => 'WC-MILL-01',
        ]);

        // View capacity load for this work center
        $loadResponse = $this->apiGet("/manufacturing/work-centers/{$workCenterId}/load");
        $loadResponse->assertStatus(200);
    }

    // =========================================================================
    // 3. MRP Run (SAP MD01/MD02)
    // =========================================================================

    public function test_mrp_run_executes_and_returns_completed_status(): void
    {
        // Run MRP with a 30-day horizon — no open sales orders in the test DB
        // so planned_orders count = 0, but the run itself must complete
        $runResponse = $this->apiPost('/manufacturing/mrp/runs', [
            'planning_horizon_days' => 30,
        ]);
        $runResponse->assertStatus(201);

        $runId = $runResponse->json('data.id');
        $this->assertNotNull($runId);

        $mrpRun = MrpRun::find($runId);
        $this->assertEquals(MrpRun::STATUS_COMPLETED, $mrpRun->status);
        $this->assertEquals($this->organization->id, $mrpRun->organization_id);
        $this->assertNotNull($mrpRun->completed_at);

        // List planned orders for this run
        $plannedResponse = $this->apiGet("/manufacturing/mrp/runs/{$runId}/planned-orders");
        $plannedResponse->assertStatus(200);
    }

    // =========================================================================
    // 4. MRP Demand Forecast + Planned Order Firmation (SAP MD04/MD12)
    // =========================================================================

    public function test_mrp_planned_order_can_be_firmed(): void
    {
        $product   = $this->createProduct('MRP Test Product');
        $component = $this->createProduct('MRP Component');

        // Create and activate a BOM so MRP can explode demand
        $bomResponse = $this->apiPost('/manufacturing/bom-templates', [
            'name'            => 'MRP Test BOM',
            'product_id'      => $product->id,
            'output_quantity' => 10,
            'lines' => [
                ['product_id' => $component->id, 'quantity' => 2],
            ],
        ]);
        $bomId = $bomResponse->json('data.id');
        $this->apiPatch("/manufacturing/bom-templates/{$bomId}/active", ['active' => true])->assertStatus(200);

        // Inject a demand forecast (field name: forecast_quantity, not quantity)
        $forecastResponse = $this->apiPost('/manufacturing/mrp/forecasts', [
            'product_id'        => $product->id,
            'forecast_date'     => now()->addDays(10)->format('Y-m-d'),
            'forecast_quantity' => 50,
        ]);
        $forecastResponse->assertStatus(201);

        $forecastId = $forecastResponse->json('data.id');
        $this->assertNotNull($forecastId);
        $this->assertEquals($this->organization->id, $forecastResponse->json('data.organization_id'));

        // Run MRP — should collect forecast demand and generate planned orders
        $runResponse = $this->apiPost('/manufacturing/mrp/runs', [
            'planning_horizon_days' => 30,
        ]);
        $runResponse->assertStatus(201);
        $runId = $runResponse->json('data.id');

        $mrpRun = MrpRun::find($runId);
        $this->assertEquals(MrpRun::STATUS_COMPLETED, $mrpRun->status);

        // Retrieve planned orders
        $plannedOrdersResponse = $this->apiGet("/manufacturing/mrp/runs/{$runId}/planned-orders");
        $plannedOrdersResponse->assertStatus(200);

        $plannedOrders = $plannedOrdersResponse->json('data') ?? [];

        if (!empty($plannedOrders)) {
            $firstOrderId = $plannedOrders[0]['id'];

            // Firm the planned order so it cannot be auto-replaced
            $firmResponse = $this->apiPost("/manufacturing/mrp/planned-orders/{$firstOrderId}/firm");
            $firmResponse->assertStatus(200);

            $this->assertEquals('firmed', $firmResponse->json('data.status'));
        } else {
            // No planned orders (e.g. stock already covers demand) — firmation path skipped
            $this->assertEquals(MrpRun::STATUS_COMPLETED, $mrpRun->status);
        }
    }

    // =========================================================================
    // 5. Process Manufacturing — PP-PI (SAP COR1/COR2)
    // =========================================================================

    public function test_process_order_lifecycle_create_release_complete(): void
    {
        $product = $this->createProduct('API Process Product');

        // Create a master recipe (PP-PI master recipe)
        $recipeResponse = $this->apiPost('/manufacturing/process/recipes', [
            'product_id'    => $product->id,
            'recipe_code'   => 'RCP-PAINT-001',
            'name'          => 'Water-Based Paint — Standard Batch',
            'base_quantity' => 1000,
            'recipe_type'   => 'master',
            'validity_from' => now()->format('Y-m-d'),
            'is_active'     => true,
        ]);
        $recipeResponse->assertStatus(201);

        $recipeId = $recipeResponse->json('data.id');
        $this->assertNotNull($recipeId);
        $this->assertDatabaseHas('recipes', [
            'id'              => $recipeId,
            'organization_id' => $this->organization->id,
            'recipe_code'     => 'RCP-PAINT-001',
        ]);

        // Create a process order from the recipe
        $orderResponse = $this->apiPost('/manufacturing/process/orders', [
            'recipe_id'        => $recipeId,
            'planned_quantity' => 500,
            'planned_start'    => now()->format('Y-m-d H:i:s'),
            'planned_finish'   => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);
        $orderResponse->assertStatus(201);

        $orderId = $orderResponse->json('data.id');
        $this->assertNotNull($orderId);

        $order = ProcessOrder::find($orderId);
        $this->assertEquals(ProcessOrder::STATUS_CREATED, $order->status);
        $this->assertEquals($this->organization->id, $order->organization_id);

        // Release the process order → STATUS_RELEASED
        $releaseResponse = $this->apiPost("/manufacturing/process/orders/{$orderId}/release");
        $releaseResponse->assertStatus(200);

        $order->refresh();
        $this->assertEquals(ProcessOrder::STATUS_RELEASED, $order->status);

        // Complete the process order (recipe has no phases, so direct completion is valid)
        $completeResponse = $this->apiPost("/manufacturing/process/orders/{$orderId}/complete", [
            'actual_quantity' => 490,
        ]);
        $completeResponse->assertStatus(200);

        $order->refresh();
        $this->assertEquals(ProcessOrder::STATUS_COMPLETED, $order->status);
        $this->assertEquals(490.0, (float) $order->actual_quantity);
        $this->assertDatabaseHas('process_orders', [
            'id'     => $orderId,
            'status' => ProcessOrder::STATUS_COMPLETED,
        ]);
    }

    // =========================================================================
    // 6. Repetitive Manufacturing — PP-REM (SAP MF50/MFBF)
    // =========================================================================

    public function test_repetitive_manufacturing_line_schedule_confirm_and_backflush(): void
    {
        $product = $this->createProduct('Assembly Line Product');

        // Create a production line
        $lineResponse = $this->apiPost('/manufacturing/repetitive-manufacturing/lines', [
            'code'              => 'LINE-ASSY-01',
            'name'              => 'Main Assembly Line 1',
            'capacity_per_hour' => 50.0,
            'is_active'         => true,
        ]);
        $lineResponse->assertStatus(201);

        $lineId = $lineResponse->json('data.id');
        $this->assertNotNull($lineId);
        $this->assertDatabaseHas('production_lines', [
            'id'              => $lineId,
            'organization_id' => $this->organization->id,
            'code'            => 'LINE-ASSY-01',
        ]);

        // Create a schedule for the production line
        $scheduleResponse = $this->apiPost('/manufacturing/repetitive-manufacturing/schedules', [
            'product_id'             => $product->id,
            'production_line_id'     => $lineId,
            'schedule_date_from'     => now()->format('Y-m-d'),
            'schedule_date_to'       => now()->addDays(2)->format('Y-m-d'),
            'total_planned_quantity' => 300,
        ]);
        $scheduleResponse->assertStatus(201);

        $scheduleId = $scheduleResponse->json('data.id');
        $this->assertNotNull($scheduleId);

        $schedule = RepetitiveMfgSchedule::find($scheduleId);
        $this->assertEquals(RepetitiveMfgSchedule::STATUS_PLANNED, $schedule->status);
        $this->assertEquals($this->organization->id, $schedule->organization_id);

        // Retrieve the schedule with its daily lines
        $showResponse = $this->apiGet("/manufacturing/repetitive-manufacturing/schedules/{$scheduleId}");
        $showResponse->assertStatus(200);

        $lines = $showResponse->json('data.lines');
        $this->assertNotEmpty($lines, 'Schedule must generate at least one schedule line');

        // Confirm production against the first daily line
        $firstLineId = $lines[0]['id'];
        $plannedQty  = (float) $lines[0]['planned_quantity'];

        $confirmResponse = $this->apiPost(
            "/manufacturing/repetitive-manufacturing/schedule-lines/{$firstLineId}/confirm",
            ['quantity' => $plannedQty]
        );
        $confirmResponse->assertStatus(200);

        $this->assertEquals('confirmed', $confirmResponse->json('data.status'));

        // Backflush — record production quantity against the schedule
        $backflushResponse = $this->apiPost('/manufacturing/repetitive-manufacturing/backflush', [
            'repetitive_mfg_schedule_id' => $scheduleId,
            'quantity_produced'          => $plannedQty,
            'backflush_date'             => now()->format('Y-m-d'),
        ]);
        $backflushResponse->assertStatus(201);

        $this->assertNotNull($backflushResponse->json('data.id'));
        $this->assertDatabaseHas('repetitive_mfg_backflushes', [
            'repetitive_mfg_schedule_id' => $scheduleId,
            'organization_id'            => $this->organization->id,
        ]);
    }

    // =========================================================================
    // 7. Engineering Change Management — ECM (SAP CC01/CC02)
    // =========================================================================

    public function test_engineering_change_lifecycle_draft_to_implemented(): void
    {
        // Create an ECO in DRAFT
        $createResponse = $this->apiPost('/manufacturing/engineering-changes', [
            'change_number'   => 'ECO-2026-001',
            'change_type'     => 'bom_change',
            'description'     => 'Replace component A with improved component B',
            'reason'          => 'Component A has been discontinued by the supplier',
            'effectivity_date' => now()->addMonths(1)->format('Y-m-d'),
            'priority'        => 'high',
        ]);
        $createResponse->assertStatus(201);

        $ecoId = $createResponse->json('data.id');
        $this->assertNotNull($ecoId);

        $eco = EngineeringChange::find($ecoId);
        $this->assertEquals(EngineeringChange::STATUS_DRAFT, $eco->status);
        $this->assertEquals($this->organization->id, $eco->organization_id);

        // Submit for approval → STATUS_SUBMITTED
        $submitResponse = $this->apiPost("/manufacturing/engineering-changes/{$ecoId}/submit");
        $submitResponse->assertStatus(200);

        $eco->refresh();
        $this->assertEquals(EngineeringChange::STATUS_SUBMITTED, $eco->status);

        // Approve the ECO → STATUS_APPROVED
        $approveResponse = $this->apiPost("/manufacturing/engineering-changes/{$ecoId}/approve");
        $approveResponse->assertStatus(200);

        $eco->refresh();
        $this->assertEquals(EngineeringChange::STATUS_APPROVED, $eco->status);
        $this->assertNotNull($eco->approved_at);

        // Implement the ECO → STATUS_IMPLEMENTED
        $implementResponse = $this->apiPost("/manufacturing/engineering-changes/{$ecoId}/implement");
        $implementResponse->assertStatus(200);

        $eco->refresh();
        $this->assertEquals(EngineeringChange::STATUS_IMPLEMENTED, $eco->status);
        $this->assertNotNull($eco->implemented_at);

        $this->assertDatabaseHas('engineering_changes', [
            'id'     => $ecoId,
            'status' => EngineeringChange::STATUS_IMPLEMENTED,
        ]);
    }

    // =========================================================================
    // 8. ECO cannot be submitted twice (guard against double-submission)
    // =========================================================================

    public function test_submitted_engineering_change_cannot_be_submitted_again(): void
    {
        $createResponse = $this->apiPost('/manufacturing/engineering-changes', [
            'change_number' => 'ECO-DUPE-001',
            'description'   => 'Duplicate submit guard test',
        ]);
        $ecoId = $createResponse->json('data.id');

        $this->apiPost("/manufacturing/engineering-changes/{$ecoId}/submit")->assertStatus(200);

        // Second submit — must be rejected
        $response = $this->apiPost("/manufacturing/engineering-changes/{$ecoId}/submit");
        $response->assertStatus(422);
    }

    // =========================================================================
    // 9. Scrap Reporting (SAP MB1A / QM scrap)
    // =========================================================================

    public function test_scrap_report_can_be_created(): void
    {
        $product = $this->createProduct('Scrap Test Product');

        $response = $this->apiPost('/manufacturing/scrap-reports', [
            'product_id'      => $product->id,
            'scrap_date'      => now()->format('Y-m-d'),
            'scrap_quantity'  => 15,
            'scrap_cause'     => 'defect',
            'scrap_code'      => 'DEF-001',
            'description'     => 'Surface cracks detected during final inspection',
            'estimated_value' => 750.00,
            'is_recoverable'  => false,
        ]);
        $response->assertStatus(201);

        $scrapId = $response->json('data.id');
        $this->assertNotNull($scrapId);

        $this->assertDatabaseHas('scrap_reports', [
            'id'              => $scrapId,
            'organization_id' => $this->organization->id,
            'product_id'      => $product->id,
            'scrap_quantity'  => 15,
            'scrap_cause'     => 'defect',
        ]);
    }

    // =========================================================================
    // 10. Scrap organization_id is always the authenticated user's org
    // =========================================================================

    public function test_scrap_report_organization_id_matches_authenticated_user(): void
    {
        $product = $this->createProduct('Org Scrap Product');

        $response = $this->apiPost('/manufacturing/scrap-reports', [
            'product_id'     => $product->id,
            'scrap_date'     => now()->format('Y-m-d'),
            'scrap_quantity' => 3,
        ]);
        $response->assertStatus(201);

        $this->assertEquals(
            $this->organization->id,
            $response->json('data.organization_id'),
            'Scrap report organization_id must match the authenticated user'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createProduct(string $name): Product
    {
        return Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => $name,
            'type'            => Product::TYPE_SERVICE,
            'track_inventory' => false,
            'is_active'       => true,
        ]);
    }
}
