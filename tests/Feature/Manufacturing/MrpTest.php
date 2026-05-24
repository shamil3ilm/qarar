<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\MrpRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class MrpTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.mrp.view',
            'manufacturing.mrp.run',
            'manufacturing.mrp.edit',
            'manufacturing.mrp.delete',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_runs(): void
    {
        MrpRun::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/mrp', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── run ─────────────────────────────────────────────────────────────────

    public function test_run_creates_mrp_run(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/mrp/run',
            ['planning_horizon_days' => 30],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('mrp_runs', [
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_run(): void
    {
        $run = MrpRun::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/mrp/{$run->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── forecasts ────────────────────────────────────────────────────────────

    public function test_forecasts_returns_data(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/mrp/forecasts', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── exceptions ───────────────────────────────────────────────────────────

    public function test_exceptions_returns_data(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/mrp/exceptions', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/mrp')->assertUnauthorized();
    }
}
