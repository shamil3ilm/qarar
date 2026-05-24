<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\OverheadKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class OverheadKeyTest extends TestCase
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

    private function makeKey(array $overrides = []): OverheadKey
    {
        return OverheadKey::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'OH-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Overhead Key',
            'overhead_type'   => 'percentage',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeKey();
        $this->makeKey();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/overhead-keys');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/overhead-keys');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_overhead_key(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/overhead-keys', [
                'code'          => 'OH-001',
                'name'          => 'Manufacturing Overhead',
                'overhead_type' => 'percentage',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/overhead-keys', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_overhead_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/overhead-keys', [
                'code'          => 'OH-001',
                'name'          => 'Test',
                'overhead_type' => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_overhead_key_details(): void
    {
        $key = $this->makeKey();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/overhead-keys/' . $key->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $key->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/overhead-keys/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_overhead_key(): void
    {
        $key = $this->makeKey(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/overhead-keys/' . $key->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $key->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_overhead_key(): void
    {
        $key = $this->makeKey();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/overhead-keys/' . $key->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('overhead_keys', ['id' => $key->id]);
    }

    // -------------------------------------------------------------------------
    // Rates
    // -------------------------------------------------------------------------

    public function test_rates_returns_empty_list(): void
    {
        $key = $this->makeKey();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/overhead-keys/' . $key->id . '/rates');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_add_rate_validates_required_fields(): void
    {
        $key = $this->makeKey();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/overhead-keys/' . $key->id . '/rates', []);

        $response->assertStatus(422);
    }

    public function test_add_rate_creates_rate(): void
    {
        $key = $this->makeKey();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/overhead-keys/' . $key->id . '/rates', [
                'validity_from' => '2025-01-01',
                'overhead_rate' => 15.00,
                'currency_code' => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/overhead-keys')->assertStatus(401);
    }
}
