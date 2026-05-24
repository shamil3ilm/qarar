<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\MrpDemandItem;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\PlannedIndependentRequirement;
use App\Services\Manufacturing\MrpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PlannedIndependentRequirementTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Model behaviour
    // ─────────────────────────────────────────────────────────────────────────

    public function test_open_quantity_equals_quantity_when_not_consumed(): void
    {
        $pir = PlannedIndependentRequirement::factory()->create([
            'organization_id'   => $this->organization->id,
            'quantity'          => 200.0,
            'consumed_quantity' => 0.0,
        ]);

        $this->assertEquals(200.0, $pir->openQuantity());
    }

    public function test_open_quantity_reduces_after_consumption(): void
    {
        $pir = PlannedIndependentRequirement::factory()->create([
            'organization_id'   => $this->organization->id,
            'quantity'          => 200.0,
            'consumed_quantity' => 50.0,
        ]);

        $this->assertEquals(150.0, $pir->openQuantity());
    }

    public function test_open_quantity_is_zero_when_fully_consumed(): void
    {
        $pir = PlannedIndependentRequirement::factory()->fullyConsumed()->create([
            'organization_id' => $this->organization->id,
            'quantity'        => 100.0,
        ]);

        $this->assertEquals(0.0, $pir->openQuantity());
    }

    public function test_consume_increments_consumed_quantity(): void
    {
        $pir = PlannedIndependentRequirement::factory()->create([
            'organization_id'   => $this->organization->id,
            'quantity'          => 100.0,
            'consumed_quantity' => 20.0,
        ]);

        $pir->consume(30.0);
        $pir->refresh();

        $this->assertEquals('50.0000', $pir->consumed_quantity);
        $this->assertEquals(50.0, $pir->openQuantity());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function test_active_scope_excludes_inactive_pirs(): void
    {
        PlannedIndependentRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active'       => true,
        ]);

        PlannedIndependentRequirement::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertEquals(1, PlannedIndependentRequirement::active()->count());
    }

    public function test_within_horizon_scope_filters_by_date(): void
    {
        $today = now()->toDateString();
        $soon  = now()->addDays(5)->toDateString();
        $far   = now()->addDays(60)->toDateString();

        PlannedIndependentRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'requirement_date' => $soon,
            'is_active'        => true,
        ]);

        PlannedIndependentRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'requirement_date' => $far,
            'is_active'        => true,
        ]);

        $results = PlannedIndependentRequirement::withinHorizon($today, now()->addDays(30)->toDateString())->get();

        $this->assertCount(1, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MRP integration — PIR drives demand
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mrp_run_includes_pir_demand(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        PlannedIndependentRequirement::factory()->create([
            'organization_id'  => $this->organization->id,
            'product_id'       => $product->id,
            'quantity'         => 300.0,
            'consumed_quantity' => 0.0,
            'requirement_date' => now()->addDays(10)->toDateString(),
            'is_active'        => true,
        ]);

        $service = app(MrpService::class);
        $run = $service->runMrp(['planning_horizon_days' => 30, 'organization_id' => $this->organization->id], $this->user->id);

        $pirDemand = MrpDemandItem::where('mrp_run_id', $run->id)
            ->where('source_type', MrpDemandItem::SOURCE_PIR)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($pirDemand);
        $this->assertEquals('300.0000', $pirDemand->required_quantity);
    }

    public function test_mrp_run_excludes_inactive_pirs(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        PlannedIndependentRequirement::factory()->inactive()->create([
            'organization_id'  => $this->organization->id,
            'product_id'       => $product->id,
            'quantity'         => 300.0,
            'requirement_date' => now()->addDays(10)->toDateString(),
        ]);

        $service = app(MrpService::class);
        $run = $service->runMrp(['planning_horizon_days' => 30, 'organization_id' => $this->organization->id], $this->user->id);

        $pirDemand = MrpDemandItem::where('mrp_run_id', $run->id)
            ->where('source_type', MrpDemandItem::SOURCE_PIR)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNull($pirDemand);
    }

    public function test_mrp_run_excludes_fully_consumed_pirs(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        PlannedIndependentRequirement::factory()->fullyConsumed()->create([
            'organization_id'  => $this->organization->id,
            'product_id'       => $product->id,
            'quantity'         => 100.0,
            'requirement_date' => now()->addDays(5)->toDateString(),
            'is_active'        => true,
        ]);

        $service = app(MrpService::class);
        $run = $service->runMrp(['planning_horizon_days' => 30, 'organization_id' => $this->organization->id], $this->user->id);

        $pirDemand = MrpDemandItem::where('mrp_run_id', $run->id)
            ->where('source_type', MrpDemandItem::SOURCE_PIR)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNull($pirDemand);
    }

    public function test_mrp_run_excludes_pirs_beyond_horizon(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        PlannedIndependentRequirement::factory()->create([
            'organization_id'  => $this->organization->id,
            'product_id'       => $product->id,
            'quantity'         => 200.0,
            'requirement_date' => now()->addDays(60)->toDateString(), // beyond 30-day horizon
            'is_active'        => true,
        ]);

        $service = app(MrpService::class);
        $run = $service->runMrp(['planning_horizon_days' => 30, 'organization_id' => $this->organization->id], $this->user->id);

        $pirDemand = MrpDemandItem::where('mrp_run_id', $run->id)
            ->where('source_type', MrpDemandItem::SOURCE_PIR)
            ->first();

        $this->assertNull($pirDemand);
    }

    public function test_source_pir_constant_is_defined(): void
    {
        $this->assertEquals('pir', MrpDemandItem::SOURCE_PIR);
    }

    public function test_partial_pir_supplies_open_quantity_to_mrp(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        PlannedIndependentRequirement::factory()->create([
            'organization_id'   => $this->organization->id,
            'product_id'        => $product->id,
            'quantity'          => 200.0,
            'consumed_quantity' => 80.0, // 120 open
            'requirement_date'  => now()->addDays(7)->toDateString(),
            'is_active'         => true,
        ]);

        $service = app(MrpService::class);
        $run = $service->runMrp(['planning_horizon_days' => 30, 'organization_id' => $this->organization->id], $this->user->id);

        $pirDemand = MrpDemandItem::where('mrp_run_id', $run->id)
            ->where('source_type', MrpDemandItem::SOURCE_PIR)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($pirDemand);
        $this->assertEquals('120.0000', $pirDemand->required_quantity);
    }
}
