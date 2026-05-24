<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\InternalOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class InternalOrderTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeOrder(array $overrides = []): InternalOrder
    {
        return InternalOrder::create(array_merge([
            'organization_id' => $this->organization->id,
            'order_number'    => 'ORD-' . fake()->unique()->numerify('######'),
            'description'     => 'Test Internal Order',
            'order_type'      => 'overhead',
            'status'          => 'created',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeOrder();
        $this->makeOrder();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/internal-orders');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/internal-orders');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_internal_order(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/internal-orders', [
                'order_number' => 'IO-2025-001',
                'description'  => 'Office renovation project',
                'order_type'   => 'overhead',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/internal-orders', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_order_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/internal-orders', [
                'order_number' => 'IO-001',
                'description'  => 'Test',
                'order_type'   => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_order_details(): void
    {
        $order = $this->makeOrder();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/internal-orders/' . $order->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/internal-orders/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_internal_order(): void
    {
        $order = $this->makeOrder(['description' => 'Old Description']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/internal-orders/' . $order->uuid, [
                'description' => 'New Description',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Description', $order->fresh()->description);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_created_order(): void
    {
        $order = $this->makeOrder(['status' => 'created']);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/internal-orders/' . $order->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('internal_orders', ['id' => $order->id]);
    }

    public function test_destroy_rejects_non_created_order(): void
    {
        $order = $this->makeOrder(['status' => 'released']);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/internal-orders/' . $order->uuid);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Release
    // -------------------------------------------------------------------------

    public function test_release_transitions_to_released(): void
    {
        $order = $this->makeOrder(['status' => 'created']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/internal-orders/' . $order->uuid . '/release');

        $response->assertStatus(200);
        $this->assertEquals('released', $order->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Technically Complete & Close
    // -------------------------------------------------------------------------

    public function test_technically_complete_transitions_released_order(): void
    {
        $order = $this->makeOrder(['status' => 'released']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/internal-orders/' . $order->uuid . '/technically-complete');

        $response->assertStatus(200);
        $this->assertEquals('technically_completed', $order->fresh()->status);
    }

    public function test_close_transitions_technically_completed_order(): void
    {
        $order = $this->makeOrder(['status' => 'technically_completed']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/internal-orders/' . $order->uuid . '/close');

        $response->assertStatus(200);
        $this->assertEquals('closed', $order->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Budget Status
    // -------------------------------------------------------------------------

    public function test_budget_status_returns_budget_info(): void
    {
        $order = $this->makeOrder(['budget_amount' => 50000]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/internal-orders/' . $order->uuid . '/budget-status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/internal-orders')->assertStatus(401);
    }
}
