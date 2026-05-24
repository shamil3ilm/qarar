<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReturnsInspectionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/returns-inspection', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_lot(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/returns-inspection',
            [
                'product_id'        => $this->product->id,
                'received_quantity' => 10,
                'return_type'       => 'customer_return',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('returns_inspection_lots', [
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);
    }

    public function test_store_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/returns-inspection',
            ['received_quantity' => 10],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/returns-inspection')->assertUnauthorized();
    }
}
