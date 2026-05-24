<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\CostingVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductCostingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.costing.view',
            'manufacturing.costing.create',
            'manufacturing.costing.run',
        ]);
    }

    // ─── costing versions ─────────────────────────────────────────────────────

    public function test_index_versions_returns_paginated(): void
    {
        CostingVersion::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/costing-versions', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_version_creates_version(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/costing-versions',
            [
                'version_code'  => 'VER-2026',
                'description'   => 'Standard Cost 2026',
                'valid_from'    => now()->format('Y-m-d'),
                'costing_type'  => 'standard',
                'currency_code' => 'USD',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('costing_versions', [
            'version_code'    => 'VER-2026',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_version_requires_version_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/costing-versions',
            [
                'description'  => 'Standard Cost',
                'valid_from'   => now()->format('Y-m-d'),
                'costing_type' => 'standard',
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_show_version_returns_version(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/costing-versions/{$version->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── cost variances ───────────────────────────────────────────────────────

    public function test_index_variances_returns_data(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/cost-variances', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── wip valuations ───────────────────────────────────────────────────────

    public function test_index_wip_valuations_returns_data(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/wip-valuations', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/costing-versions')->assertUnauthorized();
    }
}
