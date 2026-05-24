<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\LtpSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class LongTermPlanningTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated(): void
    {
        LtpSimulation::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/long-term-planning', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_simulation(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/long-term-planning',
            [
                'name'                  => 'Q4 2026 Plan',
                'planning_horizon_from' => now()->format('Y-m-d'),
                'planning_horizon_to'   => now()->addMonths(3)->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('ltp_simulations', [
            'name'            => 'Q4 2026 Plan',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/long-term-planning',
            [
                'planning_horizon_from' => now()->format('Y-m-d'),
                'planning_horizon_to'   => now()->addMonths(3)->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_simulation(): void
    {
        $sim = LtpSimulation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/long-term-planning/{$sim->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/long-term-planning')->assertUnauthorized();
    }
}
