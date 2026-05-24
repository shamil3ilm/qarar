<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProcurementInspectionTest extends TestCase
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

    // ─── configs ──────────────────────────────────────────────────────────────

    public function test_index_configs_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/procurement-inspection/configs', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_config_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/procurement-inspection/configs',
            [
                'product_id'          => $this->product->id,
                'inspection_required' => true,
                'sampling_percentage' => 10,
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
    }

    // ─── inspections ──────────────────────────────────────────────────────────

    public function test_index_inspections_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/procurement-inspection/inspections', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_inspection_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/procurement-inspection/inspections',
            [
                'product_id'        => $this->product->id,
                'quantity_received' => 50,
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
    }

    public function test_store_inspection_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/procurement-inspection/inspections',
            ['quantity_received' => 50],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/procurement-inspection/configs')->assertUnauthorized();
    }
}
