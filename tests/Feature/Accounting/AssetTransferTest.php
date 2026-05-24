<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\AssetTransfer;
use App\Models\Accounting\FixedAsset;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AssetTransferTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.assets.view',
            'accounting.assets.dispose',
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

    private function makeTransfer(FixedAsset $asset, array $overrides = []): AssetTransfer
    {
        return AssetTransfer::create(array_merge([
            'transfer_number'           => 'TRF-' . fake()->unique()->numerify('######'),
            'fixed_asset_id'            => $asset->id,
            'sending_organization_id'   => $this->organization->id,
            'receiving_organization_id' => $this->organization->id,
            'transfer_date'             => '2025-06-01',
            'transfer_type'             => 'book_value',
            'gross_value'               => (float) $asset->acquisition_cost,
            'accumulated_depreciation'  => 0,
            'net_book_value'            => (float) $asset->book_value,
            'gain_loss_amount'          => 0,
            'status'                    => AssetTransfer::STATUS_PENDING,
            'created_by'                => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-transfers');

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
            ->postJson('/api/v1/assets/' . $asset->uuid . '/transfers', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_pending_transfer(): void
    {
        $asset       = $this->makeAsset();
        $receivingOrg = Organization::create([
            'name'          => 'Receiving Org',
            'country_code'  => 'SA',
            'base_currency' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/assets/' . $asset->uuid . '/transfers', [
                'receiving_organization_id' => $receivingOrg->id,
                'transfer_date'             => '2025-06-01',
                'transfer_type'             => 'book_value',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $asset    = $this->makeAsset();
        $transfer = $this->makeTransfer($asset);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-transfers/' . $transfer->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-transfers/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public function test_cancel_validates_reason_required(): void
    {
        $asset    = $this->makeAsset();
        $transfer = $this->makeTransfer($asset);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-transfers/' . $transfer->uuid . '/cancel', []);

        $response->assertStatus(422);
    }

    public function test_cancel_cancels_pending_transfer(): void
    {
        $asset    = $this->makeAsset();
        $transfer = $this->makeTransfer($asset);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-transfers/' . $transfer->uuid . '/cancel', [
                'reason' => 'Business decision changed.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/asset-transfers')->assertStatus(401);
    }
}
