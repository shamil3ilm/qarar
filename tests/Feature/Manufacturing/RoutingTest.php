<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\RoutingHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RoutingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_routings(): void
    {
        RoutingHeader::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/routings', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_routing(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/routings',
            ['product_id' => $this->product->id],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('routing_headers', [
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);
    }

    public function test_store_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/routings',
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_routing(): void
    {
        $routing = RoutingHeader::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/routings/{$routing->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_routing(): void
    {
        $routing = RoutingHeader::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/routings/{$routing->uuid}",
            ['alternative' => '02'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_routing(): void
    {
        $routing = RoutingHeader::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/routings/{$routing->uuid}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('routing_headers', ['id' => $routing->id]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/routings')->assertUnauthorized();
    }
}
