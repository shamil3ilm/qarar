<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenancePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Plant Maintenance (PM) journey test.
 *
 * Verifies the complete maintenance lifecycle from equipment registration
 * through plan-based order generation and manual order completion.
 *
 * Scenarios:
 *   1.  Equipment registration (API)
 *   2.  Maintenance plan creation with tasks
 *   3.  Generate order from plan → order inherits equipment & tasks
 *   4.  Order lifecycle: START → complete tasks → COMPLETE
 *   5.  Direct order creation (without a plan)
 *   6.  Completed order cannot be started again
 *   7.  Equipment organization_id isolation
 */
class MaintenanceJourneyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'maintenance.equipment.view',
            'maintenance.equipment.create',
            'maintenance.equipment.edit',
            'maintenance.plans.view',
            'maintenance.plans.create',
            'maintenance.plans.edit',
            'maintenance.orders.view',
            'maintenance.orders.create',
            'maintenance.orders.edit',
        ]);
        $this->setUpOpenFiscalPeriod();

        // Enable the maintenance module (not included in default setUpOrganization list)
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code'     => 'maintenance',
            'is_enabled'      => true,
            'enabled_at'      => now(),
        ]);
    }

    // =========================================================================
    // 1. Equipment registration
    // =========================================================================

    public function test_equipment_can_be_created(): void
    {
        $response = $this->apiPost('/maintenance/equipment', [
            'equipment_number' => 'EQ-PUMP-001',
            'name'             => 'Hydraulic Pump — Unit 1',
            'manufacturer'     => 'Grundfos',
            'model'            => 'CM-5-I-R',
            'serial_number'    => 'GF2025001',
            'acquisition_date' => now()->subYear()->format('Y-m-d'),
            'acquisition_cost' => 15000.00,
            'status'           => Equipment::STATUS_ACTIVE,
        ]);

        $response->assertStatus(201);

        $equipmentId = $response->json('data.id');
        $this->assertNotNull($equipmentId);
        $this->assertDatabaseHas('equipment', [
            'id'               => $equipmentId,
            'organization_id'  => $this->organization->id,
            'equipment_number' => 'EQ-PUMP-001',
            'status'           => Equipment::STATUS_ACTIVE,
        ]);
    }

    // =========================================================================
    // 2 & 3. Maintenance plan creation + generate order from plan
    // =========================================================================

    public function test_plan_generates_maintenance_order_with_tasks(): void
    {
        // Create equipment first
        $equipResponse = $this->apiPost('/maintenance/equipment', [
            'equipment_number' => 'EQ-COMP-001',
            'name'             => 'Air Compressor',
            'status'           => Equipment::STATUS_ACTIVE,
        ]);
        $equipResponse->assertStatus(201);
        $equipmentId = $equipResponse->json('data.id');

        // Create a preventive maintenance plan with tasks
        $planResponse = $this->apiPost('/maintenance/plans', [
            'equipment_id'             => $equipmentId,
            'name'                     => 'Monthly PM — Air Compressor',
            'maintenance_type'         => MaintenancePlan::TYPE_PREVENTIVE,
            'frequency_type'           => MaintenancePlan::FREQ_MONTHLY,
            'frequency_value'          => 1,
            'estimated_duration_hours' => 2.5,
            'is_active'                => true,
            'tasks' => [
                ['description' => 'Check oil level',          'is_safety_critical' => false],
                ['description' => 'Inspect drive belt',       'is_safety_critical' => true],
                ['description' => 'Clean air filter element', 'is_safety_critical' => false],
            ],
        ]);
        $planResponse->assertStatus(201);

        $planId = $planResponse->json('data.id');
        $this->assertNotNull($planId);
        $this->assertDatabaseHas('maintenance_plans', [
            'id'              => $planId,
            'organization_id' => $this->organization->id,
            'equipment_id'    => $equipmentId,
        ]);

        // Generate order from the plan
        $orderResponse = $this->apiPost("/maintenance/plans/{$planId}/generate-order");
        $orderResponse->assertStatus(201);

        $orderId = $orderResponse->json('data.id');
        $this->assertNotNull($orderId);

        $order = MaintenanceOrder::find($orderId);
        $this->assertEquals(MaintenanceOrder::STATUS_OPEN, $order->status);
        $this->assertEquals($equipmentId, $order->equipment_id);
        $this->assertEquals($this->organization->id, $order->organization_id);
    }

    // =========================================================================
    // 4. Order lifecycle: START → complete tasks → COMPLETE
    // =========================================================================

    public function test_maintenance_order_full_lifecycle(): void
    {
        $equipResponse = $this->apiPost('/maintenance/equipment', [
            'equipment_number' => 'EQ-FAN-001',
            'name'             => 'Exhaust Fan',
            'status'           => Equipment::STATUS_ACTIVE,
        ]);
        $equipmentId = $equipResponse->json('data.id');

        // Create order directly with tasks
        $orderResponse = $this->apiPost('/maintenance/orders', [
            'equipment_id' => $equipmentId,
            'order_type'   => MaintenanceOrder::TYPE_CORRECTIVE,
            'priority'     => MaintenanceOrder::PRIORITY_HIGH,
            'description'  => 'Fan bearing replacement — noisy operation',
            'tasks' => [
                ['task_description' => 'Isolate electrical supply', 'is_safety_critical' => true,  'sort_order' => 1],
                ['task_description' => 'Remove fan assembly',       'is_safety_critical' => false, 'sort_order' => 2],
                ['task_description' => 'Replace bearing',           'is_safety_critical' => false, 'sort_order' => 3],
                ['task_description' => 'Reassemble and test',       'is_safety_critical' => false, 'sort_order' => 4],
            ],
        ]);
        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        $order = MaintenanceOrder::find($orderId);
        $this->assertEquals(MaintenanceOrder::STATUS_OPEN, $order->status);
        $this->assertCount(4, $order->tasks);

        // Start the order → IN_PROGRESS
        $startResponse = $this->apiPost("/maintenance/orders/{$orderId}/start");
        $startResponse->assertStatus(200);

        $order->refresh();
        $this->assertEquals(MaintenanceOrder::STATUS_IN_PROGRESS, $order->status);

        // Complete each task
        foreach ($order->tasks as $task) {
            $taskResponse = $this->apiPost(
                "/maintenance/orders/{$orderId}/tasks/{$task->id}/complete",
                ['notes' => "Task {$task->id} completed during journey test"]
            );
            $taskResponse->assertStatus(200);

            $task->refresh();
            $this->assertTrue((bool) $task->is_completed, "Task {$task->id} must be marked completed");
            $this->assertNotNull($task->completed_at);
        }

        // Complete the order
        $completeResponse = $this->apiPost("/maintenance/orders/{$orderId}/complete", [
            'resolution_notes' => 'All tasks done. Fan operates normally.',
            'actual_cost'      => 1250.00,
            'downtime_hours'   => 3.5,
        ]);
        $completeResponse->assertStatus(200);

        $order->refresh();
        $this->assertEquals(MaintenanceOrder::STATUS_COMPLETED, $order->status);
        $this->assertEquals($this->organization->id, $order->organization_id);
        $this->assertDatabaseHas('maintenance_orders', [
            'id'     => $orderId,
            'status' => MaintenanceOrder::STATUS_COMPLETED,
        ]);
    }

    // =========================================================================
    // 5. Direct order creation without a plan
    // =========================================================================

    public function test_direct_order_creation_without_plan(): void
    {
        $equipResponse = $this->apiPost('/maintenance/equipment', [
            'equipment_number' => 'EQ-VALVE-001',
            'name'             => 'Pressure Relief Valve',
            'status'           => Equipment::STATUS_ACTIVE,
        ]);
        $equipmentId = $equipResponse->json('data.id');

        $orderResponse = $this->apiPost('/maintenance/orders', [
            'equipment_id' => $equipmentId,
            'order_type'   => MaintenanceOrder::TYPE_EMERGENCY,
            'priority'     => MaintenanceOrder::PRIORITY_CRITICAL,
            'description'  => 'Pressure relief valve stuck open — emergency fix',
        ]);
        $orderResponse->assertStatus(201);

        $orderId = $orderResponse->json('data.id');
        $order   = MaintenanceOrder::find($orderId);

        $this->assertEquals(MaintenanceOrder::STATUS_OPEN, $order->status);
        $this->assertNull($order->maintenance_plan_id);
        $this->assertEquals(MaintenanceOrder::PRIORITY_CRITICAL, $order->priority);
        $this->assertEquals($this->organization->id, $order->organization_id);
    }

    // =========================================================================
    // 6. Completed order cannot be started again
    // =========================================================================

    public function test_completed_order_cannot_be_started_again(): void
    {
        $equipResponse = $this->apiPost('/maintenance/equipment', [
            'equipment_number' => 'EQ-MOTOR-001',
            'name'             => 'Drive Motor',
            'status'           => Equipment::STATUS_ACTIVE,
        ]);
        $equipmentId = $equipResponse->json('data.id');

        $orderResponse = $this->apiPost('/maintenance/orders', [
            'equipment_id' => $equipmentId,
            'order_type'   => MaintenanceOrder::TYPE_PREVENTIVE,
            'description'  => 'Routine lubrication',
        ]);
        $orderId = $orderResponse->json('data.id');

        $this->apiPost("/maintenance/orders/{$orderId}/start")->assertStatus(200);
        $this->apiPost("/maintenance/orders/{$orderId}/complete")->assertStatus(200);

        // Attempt to start a completed order — must be rejected
        $response = $this->apiPost("/maintenance/orders/{$orderId}/start");
        $response->assertStatus(422);
    }

    // =========================================================================
    // 7. Equipment organization_id isolation
    // =========================================================================

    public function test_equipment_organization_id_is_always_set(): void
    {
        $response = $this->apiPost('/maintenance/equipment', [
            'equipment_number' => 'EQ-ISO-001',
            'name'             => 'Isolation Test Equipment',
            'status'           => Equipment::STATUS_ACTIVE,
        ]);
        $response->assertStatus(201);

        $equipmentId = $response->json('data.id');
        $equipment   = Equipment::find($equipmentId);

        $this->assertNotNull($equipment->organization_id, 'organization_id must never be null');
        $this->assertEquals($this->organization->id, $equipment->organization_id);
    }
}
