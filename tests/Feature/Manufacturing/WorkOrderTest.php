<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Product $product;
    private BomTemplate $activeBom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.workorders.view',
            'manufacturing.workorders.create',
            'manufacturing.workorders.edit',
            'manufacturing.workorders.delete',
            'manufacturing.workorders.start',
            'manufacturing.workorders.complete',
            'manufacturing.workorders.cancel',
            'manufacturing.workorders.produce',
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->activeBom = BomTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);
    }

    private function makeWorkOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'bom_template_id'    => $this->activeBom->id,
            'planned_quantity'   => 100,
            'planned_start_date' => now()->addDay()->format('Y-m-d'),
            'planned_end_date'   => now()->addDays(5)->format('Y-m-d'),
            'priority'           => 'normal',
        ], $overrides);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_work_orders(): void
    {
        WorkOrder::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->activeBom->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/work-orders', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_draft_work_order(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/work-orders',
            $this->makeWorkOrderPayload(),
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_orders', [
            'organization_id' => $this->organization->id,
            'status'          => WorkOrder::STATUS_DRAFT,
        ]);
    }

    public function test_store_requires_bom_template_id(): void
    {
        $payload = $this->makeWorkOrderPayload();
        unset($payload['bom_template_id']);

        $response = $this->postJson('/api/v1/manufacturing/work-orders', $payload, $this->authHeaders());

        $response->assertUnprocessable();
    }

    public function test_store_requires_planned_quantity(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/work-orders',
            $this->makeWorkOrderPayload(['planned_quantity' => null]),
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_planned_start_date(): void
    {
        $payload = $this->makeWorkOrderPayload();
        unset($payload['planned_start_date']);

        $response = $this->postJson('/api/v1/manufacturing/work-orders', $payload, $this->authHeaders());

        $response->assertUnprocessable();
    }

    public function test_store_rejects_bom_from_other_org(): void
    {
        $otherBom = BomTemplate::factory()->active()->create(); // different org

        $response = $this->postJson(
            '/api/v1/manufacturing/work-orders',
            $this->makeWorkOrderPayload(['bom_template_id' => $otherBom->id]),
            $this->authHeaders()
        );

        $response->assertStatus(422);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->activeBom->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/work-orders/{$wo->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_other_org(): void
    {
        $wo = WorkOrder::factory()->create(); // different org

        $response = $this->getJson(
            "/api/v1/manufacturing/work-orders/{$wo->uuid}",
            $this->authHeaders()
        );

        $response->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_priority(): void
    {
        $wo = WorkOrder::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->activeBom->id,
            'product_id'      => $this->product->id,
            'priority'        => WorkOrder::PRIORITY_NORMAL,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/work-orders/{$wo->uuid}",
            ['priority' => 'high'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_orders', [
            'id'       => $wo->id,
            'priority' => 'high',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_draft_work_order(): void
    {
        $wo = WorkOrder::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->activeBom->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/work-orders/{$wo->uuid}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('work_orders', ['id' => $wo->id]);
    }

    public function test_destroy_rejects_non_draft_work_order(): void
    {
        $wo = WorkOrder::factory()->inProgress()->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->activeBom->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/work-orders/{$wo->uuid}",
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── release ──────────────────────────────────────────────────────────────

    public function test_release_transitions_draft_to_released(): void
    {
        $wo = WorkOrder::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->activeBom->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/work-orders/{$wo->uuid}/release",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_orders', [
            'id'     => $wo->id,
            'status' => WorkOrder::STATUS_RELEASED,
        ]);
    }

    // ─── cancel ───────────────────────────────────────────────────────────────

    public function test_cancel_transitions_draft_to_cancelled(): void
    {
        $wo = WorkOrder::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->activeBom->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/work-orders/{$wo->uuid}/cancel",
            ['reason' => 'No longer needed'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_orders', [
            'id'     => $wo->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);
    }

    // ─── statistics ───────────────────────────────────────────────────────────

    public function test_statistics_returns_data(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/work-orders/statistics',
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── production schedule ──────────────────────────────────────────────────

    public function test_production_schedule_returns_data(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/work-orders/production-schedule?start_date=' . now()->format('Y-m-d') . '&end_date=' . now()->addMonth()->format('Y-m-d'),
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_production_schedule_requires_dates(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/work-orders/production-schedule',
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/work-orders')->assertUnauthorized();
    }
}
