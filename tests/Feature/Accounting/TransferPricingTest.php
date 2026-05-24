<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\TransferPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TransferPricingTest extends TestCase
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

    private function makePrice(array $overrides = []): TransferPrice
    {
        return TransferPrice::create(array_merge([
            'organization_id'        => $this->organization->id,
            'transfer_price_method'  => TransferPrice::METHOD_STANDARD_COST,
            'base_price'             => 100.00,
            'effective_from'         => '2025-01-01',
            'currency_code'          => 'SAR',
            'is_active'              => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_list(): void
    {
        $this->makePrice();
        $this->makePrice();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_transfer_price(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing', [
                'transfer_price_method' => TransferPrice::METHOD_COST_PLUS,
                'base_price'            => 200.00,
                'markup_percentage'     => 15.0,
                'effective_from'        => '2025-01-01',
                'currency_code'         => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_method_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing', [
                'transfer_price_method' => 'invalid_method',
                'base_price'            => 100.00,
                'effective_from'        => '2025-01-01',
                'currency_code'         => 'SAR',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $price = $this->makePrice();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/' . $price->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_transfer_price(): void
    {
        $price = $this->makePrice(['base_price' => 100.00]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/transfer-pricing/' . $price->id, [
                'base_price' => 250.00,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(250.00, (float) $price->fresh()->base_price);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_transfer_price(): void
    {
        $price = $this->makePrice();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/transfer-pricing/' . $price->id);

        $response->assertStatus(200);
        $this->assertNull(TransferPrice::find($price->id));
    }

    // -------------------------------------------------------------------------
    // Versions
    // -------------------------------------------------------------------------

    public function test_versions_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/versions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_store_version_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions', []);

        $response->assertStatus(422);
    }

    public function test_store_version_creates_version(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions', [
                'version_name' => 'FY2025 Version 1',
                'fiscal_year'  => 2025,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_activate_version_returns_success(): void
    {
        $version = \App\Models\Accounting\TransferPriceVersion::create([
            'organization_id' => $this->organization->id,
            'version_name'    => 'FY2025 v1',
            'fiscal_year'     => 2025,
            'status'          => \App\Models\Accounting\TransferPriceVersion::STATUS_DRAFT,
            'created_by'      => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions/' . $version->id . '/activate');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Calculate
    // -------------------------------------------------------------------------

    public function test_calculate_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/calculate', []);

        $response->assertStatus(422);
    }

    public function test_calculate_also_validates_missing_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/calculate', [
                'quantity' => 10,
                // missing product_id, from/to_profit_center_id, date
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/transfer-pricing')->assertStatus(401);
    }
}
