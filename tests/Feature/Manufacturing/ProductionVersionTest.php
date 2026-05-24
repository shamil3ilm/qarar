<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\ProductionVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductionVersionTest extends TestCase
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

    public function test_index_returns_paginated_versions(): void
    {
        ProductionVersion::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/production-versions', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_version(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/production-versions',
            [
                'product_id'   => $this->product->id,
                'version_code' => 'V01',
                'valid_from'   => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('production_versions', [
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'version_code'    => 'V01',
        ]);
    }

    public function test_store_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/production-versions',
            ['version_code' => 'V01', 'valid_from' => now()->format('Y-m-d')],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_version_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/production-versions',
            ['product_id' => $this->product->id, 'valid_from' => now()->format('Y-m-d')],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_valid_from(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/production-versions',
            ['product_id' => $this->product->id, 'version_code' => 'V01'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_rejects_product_from_other_org(): void
    {
        $otherProduct = Product::factory()->create();

        $response = $this->postJson(
            '/api/v1/manufacturing/production-versions',
            [
                'product_id'   => $otherProduct->id,
                'version_code' => 'V01',
                'valid_from'   => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_version(): void
    {
        $version = ProductionVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/production-versions/{$version->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_description(): void
    {
        $version = ProductionVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/production-versions/{$version->id}",
            ['description' => 'Updated description'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('production_versions', [
            'id'          => $version->id,
            'description' => 'Updated description',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_version(): void
    {
        $version = ProductionVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/production-versions/{$version->id}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('production_versions', ['id' => $version->id]);
    }

    // ─── setDefault ───────────────────────────────────────────────────────────

    public function test_set_default_marks_version_as_default(): void
    {
        $version = ProductionVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'is_default'      => false,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/production-versions/{$version->id}/set-default",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('production_versions', [
            'id'         => $version->id,
            'is_default' => true,
        ]);
    }

    // ─── forProduct ───────────────────────────────────────────────────────────

    public function test_for_product_returns_versions(): void
    {
        ProductionVersion::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/production-versions/product/{$this->product->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/production-versions')->assertUnauthorized();
    }
}
