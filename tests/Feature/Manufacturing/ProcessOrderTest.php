<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProcessOrderTest extends TestCase
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

    // ─── recipes ──────────────────────────────────────────────────────────────

    public function test_index_recipes_returns_paginated(): void
    {
        Recipe::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/process/recipes', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_recipe_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/process/recipes',
            [
                'product_id'    => $this->product->id,
                'recipe_code'   => 'RCP-001',
                'name'          => 'Base Recipe',
                'base_quantity' => 100,
                'validity_from' => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('recipes', [
            'recipe_code'     => 'RCP-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_recipe_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/process/recipes',
            [
                'recipe_code'   => 'RCP-002',
                'name'          => 'No Product',
                'base_quantity' => 100,
                'validity_from' => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── orders ───────────────────────────────────────────────────────────────

    public function test_index_orders_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/process/orders', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/process/recipes')->assertUnauthorized();
    }
}
