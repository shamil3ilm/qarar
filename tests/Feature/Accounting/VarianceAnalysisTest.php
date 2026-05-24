<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\VarianceAnalysisRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class VarianceAnalysisTest extends TestCase
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

    private function makeRun(array $overrides = []): VarianceAnalysisRun
    {
        return VarianceAnalysisRun::create(array_merge([
            'organization_id' => $this->organization->id,
            'period'          => 3,
            'fiscal_year'     => 2025,
            'run_type'        => VarianceAnalysisRun::RUN_TYPE_COST_CENTER,
            'run_by'          => $this->user->id,
            'status'          => 'completed',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/variance-analysis', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_run_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/variance-analysis', [
                'period'      => 3,
                'fiscal_year' => 2025,
                'run_type'    => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_run(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/variance-analysis', [
                'period'      => 3,
                'fiscal_year' => 2025,
                'run_type'    => VarianceAnalysisRun::RUN_TYPE_COST_CENTER,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_run_details(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $run->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Results
    // -------------------------------------------------------------------------

    public function test_results_returns_data(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/' . $run->id . '/results');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    public function test_summary_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/summary');

        $response->assertStatus(422);
    }

    public function test_summary_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/variance-analysis/summary?period=3&fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/variance-analysis')->assertStatus(401);
    }
}
