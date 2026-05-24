<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AssetTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.asset_categories.view',
            'accounting.asset_categories.create',
            'accounting.asset_categories.update',
            'accounting.asset_categories.delete',
            'accounting.assets.view',
            'accounting.assets.create',
            'accounting.assets.update',
            'accounting.assets.delete',
            'accounting.assets.dispose',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCategory(array $overrides = []): AssetCategory
    {
        return AssetCategory::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
        ], $overrides));
    }

    private function makeAsset(array $overrides = []): FixedAsset
    {
        $category = $this->makeCategory();
        $cost     = 10000.0;

        return FixedAsset::factory()->create(array_merge([
            'organization_id'          => $this->organization->id,
            'asset_category_id'        => $category->id,
            'acquisition_cost'         => $cost,
            'book_value'               => $cost,
            'accumulated_depreciation' => 0,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Asset Categories — CRUD
    // -------------------------------------------------------------------------

    public function test_categories_index_returns_list(): void
    {
        $this->makeCategory(['name' => 'Vehicles']);
        $this->makeCategory(['name' => 'Computers']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_categories_store_creates_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-categories', [
                'name'                        => 'IT Equipment',
                'code'                        => 'ITE',
                'default_useful_life_years'   => 3,
                'default_depreciation_method' => 'straight_line',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'IT Equipment')
            ->assertJsonPath('data.code', 'ITE');
    }

    public function test_categories_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-categories', []);

        $response->assertStatus(422);
    }

    public function test_categories_show_returns_category(): void
    {
        $category = $this->makeCategory();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-categories/' . $category->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_categories_update_modifies_category(): void
    {
        $category = $this->makeCategory(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/asset-categories/' . $category->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_categories_destroy_deletes_category(): void
    {
        $category = $this->makeCategory();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/asset-categories/' . $category->uuid);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('asset_categories', ['id' => $category->id]);
    }

    // -------------------------------------------------------------------------
    // Fixed Assets — CRUD
    // -------------------------------------------------------------------------

    public function test_assets_index_returns_paginated_list(): void
    {
        $this->makeAsset();
        $this->makeAsset();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_assets_store_creates_asset(): void
    {
        $category = $this->makeCategory();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/assets', [
                'asset_category_id' => $category->id,
                'name'              => 'Company Laptop',
                'acquisition_date'  => '2025-01-15',
                'acquisition_cost'  => 3500.00,
                'useful_life_years' => 3,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Company Laptop');
    }

    public function test_assets_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/assets', []);

        $response->assertStatus(422);
    }

    public function test_assets_show_returns_asset(): void
    {
        $asset = $this->makeAsset();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets/' . $asset->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id);
    }

    public function test_assets_update_modifies_asset(): void
    {
        $asset = $this->makeAsset(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/assets/' . $asset->uuid, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_assets_update_rejects_disposed_asset(): void
    {
        $asset = $this->makeAsset(['status' => FixedAsset::STATUS_DISPOSED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/assets/' . $asset->uuid, ['name' => 'New Name']);

        $response->assertStatus(400);
    }

    public function test_assets_destroy_deletes_undepreciated_asset(): void
    {
        $asset = $this->makeAsset(['accumulated_depreciation' => 0]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/assets/' . $asset->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('fixed_assets', ['id' => $asset->id]);
    }

    public function test_assets_destroy_rejects_depreciated_asset(): void
    {
        $asset = $this->makeAsset(['accumulated_depreciation' => 1000]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/assets/' . $asset->uuid);

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Depreciation schedule
    // -------------------------------------------------------------------------

    public function test_schedule_returns_depreciation_periods(): void
    {
        $asset = $this->makeAsset([
            'acquisition_cost'  => 12000,
            'book_value'        => 12000,
            'salvage_value'     => 0,
            'useful_life_years' => 3,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets/' . $asset->uuid . '/schedule');

        $response->assertStatus(200)
            ->assertJsonPath('data.asset_id', $asset->id);

        $this->assertNotEmpty($response->json('data.schedule'));
    }

    // -------------------------------------------------------------------------
    // Only own-organization assets visible
    // -------------------------------------------------------------------------

    public function test_index_excludes_other_org_assets(): void
    {
        $otherOrg      = \App\Models\Core\Organization::factory()->create();
        $otherCategory = AssetCategory::factory()->create(['organization_id' => $otherOrg->id]);
        FixedAsset::factory()->create([
            'organization_id'  => $otherOrg->id,
            'asset_category_id' => $otherCategory->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/assets')->assertStatus(401);
        $this->getJson('/api/v1/asset-categories')->assertStatus(401);
    }
}
