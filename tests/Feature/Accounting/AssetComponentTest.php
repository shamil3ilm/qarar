<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\AssetComponent;
use App\Models\Accounting\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AssetComponentTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.assets.view',
            'accounting.assets.create',
            'accounting.assets.dispose',
            'accounting.assets.delete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAsset(array $overrides = []): FixedAsset
    {
        $category = AssetCategory::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $cost = 50000.0;

        return FixedAsset::factory()->create(array_merge([
            'organization_id'          => $this->organization->id,
            'asset_category_id'        => $category->id,
            'acquisition_cost'         => $cost,
            'book_value'               => $cost,
            'accumulated_depreciation' => 0,
            'status'                   => FixedAsset::STATUS_ACTIVE,
        ], $overrides));
    }

    private function makeComponent(FixedAsset $asset, array $overrides = []): AssetComponent
    {
        return AssetComponent::create(array_merge([
            'organization_id'    => $this->organization->id,
            'fixed_asset_id'     => $asset->id,
            'component_number'   => 'COMP-' . fake()->unique()->numerify('###'),
            'name'               => 'Test Component',
            'acquisition_date'   => '2025-01-01',
            'acquisition_cost'   => 5000.00,
            'salvage_value'      => 0,
            'useful_life_years'  => 5,
            'accumulated_depreciation' => 0,
            'book_value'         => 5000.00,
            'depreciation_method' => 'straight_line',
            'status'             => AssetComponent::STATUS_ACTIVE,
            'created_by'         => $this->user->id,
        ], $overrides));
    }

    private function assetUrl(FixedAsset $asset): string
    {
        return '/api/v1/assets/' . $asset->uuid . '/components';
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $asset = $this->makeAsset();
        $this->makeComponent($asset);

        $response = $this->withToken($this->token)
            ->getJson($this->assetUrl($asset));

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $asset = $this->makeAsset();

        $response = $this->withToken($this->token)
            ->postJson($this->assetUrl($asset), []);

        $response->assertStatus(422);
    }

    public function test_store_adds_component_to_asset(): void
    {
        $asset = $this->makeAsset();

        $response = $this->withToken($this->token)
            ->postJson($this->assetUrl($asset), [
                'name'             => 'Engine',
                'acquisition_date' => '2025-03-01',
                'acquisition_cost' => 8000.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_rejects_disposed_asset(): void
    {
        $asset = $this->makeAsset(['status' => FixedAsset::STATUS_DISPOSED]);

        $response = $this->withToken($this->token)
            ->postJson($this->assetUrl($asset), [
                'name'             => 'Engine',
                'acquisition_date' => '2025-03-01',
                'acquisition_cost' => 8000.00,
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $asset     = $this->makeAsset();
        $component = $this->makeComponent($asset);

        $response = $this->withToken($this->token)
            ->getJson($this->assetUrl($asset) . '/' . $component->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_wrong_asset(): void
    {
        $asset1     = $this->makeAsset();
        $asset2     = $this->makeAsset();
        $component  = $this->makeComponent($asset1);

        $response = $this->withToken($this->token)
            ->getJson($this->assetUrl($asset2) . '/' . $component->uuid);

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Retire
    // -------------------------------------------------------------------------

    public function test_retire_validates_required_fields(): void
    {
        $asset     = $this->makeAsset();
        $component = $this->makeComponent($asset);

        $response = $this->withToken($this->token)
            ->postJson($this->assetUrl($asset) . '/' . $component->uuid . '/retire', []);

        $response->assertStatus(422);
    }

    public function test_retire_retires_component(): void
    {
        $asset     = $this->makeAsset();
        $component = $this->makeComponent($asset);

        $response = $this->withToken($this->token)
            ->postJson($this->assetUrl($asset) . '/' . $component->uuid . '/retire', [
                'retirement_date' => '2025-06-30',
                'reason'          => 'End of useful life',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_active_component(): void
    {
        $asset     = $this->makeAsset();
        $component = $this->makeComponent($asset);

        $response = $this->withToken($this->token)
            ->deleteJson($this->assetUrl($asset) . '/' . $component->uuid);

        $response->assertStatus(200);
        $this->assertNull(AssetComponent::find($component->id));
    }

    public function test_destroy_rejects_retired_component(): void
    {
        $asset     = $this->makeAsset();
        $component = $this->makeComponent($asset, ['status' => AssetComponent::STATUS_RETIRED]);

        $response = $this->withToken($this->token)
            ->deleteJson($this->assetUrl($asset) . '/' . $component->uuid);

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $asset = $this->makeAsset();

        $this->getJson($this->assetUrl($asset))->assertStatus(401);
    }
}
