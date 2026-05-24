<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\QualityNotification;
use App\Models\Manufacturing\QualityPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class QualityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.create',
            'manufacturing.quality.edit',
            'manufacturing.quality.delete',
            'manufacturing.quality.inspect',
            'manufacturing.quality.resolve',
        ]);
    }

    // ─── quality plans ────────────────────────────────────────────────────────

    public function test_index_plans_returns_paginated(): void
    {
        QualityPlan::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/quality/plans', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_plan_creates_plan(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality/plans',
            ['name' => 'Receiving Inspection Plan', 'inspection_stage' => 'goods_receipt'],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('quality_plans', [
            'name'            => 'Receiving Inspection Plan',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_plan_requires_name(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality/plans',
            ['inspection_stage' => 'goods_receipt'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_show_plan_returns_plan(): void
    {
        $plan = QualityPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/quality/plans/{$plan->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_update_plan(): void
    {
        $plan = QualityPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/quality/plans/{$plan->id}",
            ['name' => 'Updated Plan'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('quality_plans', ['id' => $plan->id, 'name' => 'Updated Plan']);
    }

    public function test_destroy_plan_soft_deletes(): void
    {
        $plan = QualityPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/quality/plans/{$plan->id}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('quality_plans', ['id' => $plan->id]);
    }

    // ─── inspection lots ──────────────────────────────────────────────────────

    public function test_index_lots_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/quality/inspection-lots', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_lot_creates_lot(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->postJson(
            '/api/v1/manufacturing/quality/inspection-lots',
            ['product_id' => $product->id, 'quantity' => 100],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('inspection_lots', [
            'organization_id' => $this->organization->id,
            'product_id'      => $product->id,
        ]);
    }

    public function test_store_lot_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality/inspection-lots',
            ['quantity' => 100],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_show_lot_returns_lot(): void
    {
        $lot = InspectionLot::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/quality/inspection-lots/{$lot->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── quality notifications ────────────────────────────────────────────────

    public function test_index_notifications_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/quality/notifications', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_notification_creates_notification(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality/notifications',
            ['title' => 'Surface defect', 'description' => 'Scratch on product surface'],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('quality_notifications', [
            'organization_id' => $this->organization->id,
            'title'           => 'Surface defect',
        ]);
    }

    public function test_store_notification_requires_title(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality/notifications',
            ['description' => 'Missing title'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_show_notification_returns_notification(): void
    {
        $notification = QualityNotification::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/quality/notifications/{$notification->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── stats ────────────────────────────────────────────────────────────────

    public function test_stats_returns_data(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/quality/stats?from=' . now()->subMonth()->format('Y-m-d') . '&to=' . now()->format('Y-m-d'),
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_stats_requires_dates(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/quality/stats', $this->authHeaders());

        $response->assertUnprocessable();
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/quality/plans')->assertUnauthorized();
    }
}
