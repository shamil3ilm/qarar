<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\UsageDecision;
use App\Services\Manufacturing\QualityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class UsageDecisionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private QualityManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->service = app(QualityManagementService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeLot(float $qty = 100.0, string $status = 'pending'): InspectionLot
    {
        $product   = Product::factory()->create(['organization_id' => $this->organization->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        return InspectionLot::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $product->id,
            'warehouse_id'    => $warehouse->id,
            'quantity'        => $qty,
            'status'          => $status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Full accept
    // ─────────────────────────────────────────────────────────────────────────

    public function test_full_accept_creates_usage_decision_with_accept_code(): void
    {
        $lot = $this->makeLot(100.0);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 100.0,
            'qty_blocked'      => 0.0,
            'qty_scrap'        => 0.0,
        ], $this->user->id);

        $this->assertEquals(UsageDecision::DECISION_ACCEPT, $decision->decision_code);
        $this->assertEquals('100.0000', $decision->qty_unrestricted);
        $this->assertEquals('0.0000', $decision->qty_blocked);
        $this->assertEquals('0.0000', $decision->qty_scrap);
    }

    public function test_full_accept_transitions_lot_to_accepted(): void
    {
        $lot = $this->makeLot(100.0);

        $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 100.0,
            'qty_blocked'      => 0.0,
            'qty_scrap'        => 0.0,
        ], $this->user->id);

        $lot->refresh();
        $this->assertEquals(InspectionLot::STATUS_ACCEPTED, $lot->status);
        $this->assertEquals('100.0000', $lot->accepted_quantity);
        $this->assertEquals('0.0000', $lot->rejected_quantity);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Full reject (all to blocked)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_full_reject_to_blocked_creates_reject_decision(): void
    {
        $lot = $this->makeLot(50.0);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 0.0,
            'qty_blocked'      => 50.0,
            'qty_scrap'        => 0.0,
        ], $this->user->id);

        $this->assertEquals(UsageDecision::DECISION_REJECT, $decision->decision_code);
        $this->assertEquals('50.0000', $decision->qty_blocked);
    }

    public function test_full_reject_to_blocked_transitions_lot_to_rejected(): void
    {
        $lot = $this->makeLot(50.0);

        $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 0.0,
            'qty_blocked'      => 50.0,
            'qty_scrap'        => 0.0,
        ], $this->user->id);

        $lot->refresh();
        $this->assertEquals(InspectionLot::STATUS_REJECTED, $lot->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scrap
    // ─────────────────────────────────────────────────────────────────────────

    public function test_full_reject_to_scrap_creates_reject_decision(): void
    {
        $lot = $this->makeLot(30.0);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 0.0,
            'qty_blocked'      => 0.0,
            'qty_scrap'        => 30.0,
        ], $this->user->id);

        $this->assertEquals(UsageDecision::DECISION_REJECT, $decision->decision_code);
        $this->assertEquals('30.0000', $decision->qty_scrap);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Partial decision
    // ─────────────────────────────────────────────────────────────────────────

    public function test_partial_decision_creates_partial_code_and_transitions_lot(): void
    {
        $lot = $this->makeLot(100.0);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 70.0,
            'qty_blocked'      => 20.0,
            'qty_scrap'        => 10.0,
        ], $this->user->id);

        $this->assertEquals(UsageDecision::DECISION_PARTIAL, $decision->decision_code);

        $lot->refresh();
        $this->assertEquals(InspectionLot::STATUS_PARTIAL_ACCEPT, $lot->status);
        $this->assertEquals('70.0000', $lot->accepted_quantity);
        $this->assertEquals('30.0000', $lot->rejected_quantity); // 20 + 10
    }

    // ─────────────────────────────────────────────────────────────────────────
    // totalQuantity helper
    // ─────────────────────────────────────────────────────────────────────────

    public function test_total_quantity_sums_all_stock_types(): void
    {
        $lot = $this->makeLot(100.0);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 70.0,
            'qty_blocked'      => 20.0,
            'qty_scrap'        => 10.0,
        ], $this->user->id);

        $this->assertEquals(100.0, $decision->totalQuantity());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Decision number is generated
    // ─────────────────────────────────────────────────────────────────────────

    public function test_decision_number_is_generated(): void
    {
        $lot = $this->makeLot(100.0);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 100.0,
            'qty_blocked'      => 0.0,
            'qty_scrap'        => 0.0,
        ], $this->user->id);

        $this->assertNotEmpty($decision->decision_number);
        $this->assertStringStartsWith('UD-', $decision->decision_number);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validation guards
    // ─────────────────────────────────────────────────────────────────────────

    public function test_throws_when_quantities_exceed_lot(): void
    {
        $lot = $this->makeLot(100.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds lot quantity');

        $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 80.0,
            'qty_blocked'      => 30.0, // 110 > 100
            'qty_scrap'        => 0.0,
        ], $this->user->id);
    }

    public function test_throws_when_lot_already_completed(): void
    {
        $lot = $this->makeLot(100.0, InspectionLot::STATUS_ACCEPTED);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pending or in-inspection');

        $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 100.0,
            'qty_blocked'      => 0.0,
            'qty_scrap'        => 0.0,
        ], $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Works on in-inspection lots too
    // ─────────────────────────────────────────────────────────────────────────

    public function test_accepts_in_inspection_lot(): void
    {
        $lot = $this->makeLot(100.0, InspectionLot::STATUS_IN_INSPECTION);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 100.0,
            'qty_blocked'      => 0.0,
            'qty_scrap'        => 0.0,
        ], $this->user->id);

        $this->assertDatabaseHas('usage_decisions', ['id' => $decision->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DB persistence
    // ─────────────────────────────────────────────────────────────────────────

    public function test_usage_decision_is_persisted_to_database(): void
    {
        $lot = $this->makeLot(200.0);

        $decision = $this->service->recordUsageDecision($lot, [
            'qty_unrestricted' => 150.0,
            'qty_blocked'      => 30.0,
            'qty_scrap'        => 20.0,
            'notes'            => 'Minor surface defects scrapped, major batch blocked for rework.',
        ], $this->user->id);

        $this->assertDatabaseHas('usage_decisions', [
            'id'               => $decision->id,
            'decision_code'    => UsageDecision::DECISION_PARTIAL,
            'qty_unrestricted' => '150.0000',
            'qty_blocked'      => '30.0000',
            'qty_scrap'        => '20.0000',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Movement type constants
    // ─────────────────────────────────────────────────────────────────────────

    public function test_movement_constants_match_sap_movement_types(): void
    {
        $this->assertEquals('321', UsageDecision::MOVEMENT_UNRESTRICTED);
        $this->assertEquals('346', UsageDecision::MOVEMENT_BLOCKED);
        $this->assertEquals('551', UsageDecision::MOVEMENT_SCRAP);
    }
}
