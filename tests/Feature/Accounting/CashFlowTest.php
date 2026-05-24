<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CashFlowForecast;
use App\Models\Accounting\CashFlowScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.cash-flow.view',
            'accounting.cash-flow.generate',
            'accounting.cash-flow.manage',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeScenario(array $overrides = []): CashFlowScenario
    {
        return CashFlowScenario::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Base Scenario',
            'is_base_case'    => false,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Scenarios — index
    // -------------------------------------------------------------------------

    public function test_index_scenarios_returns_list(): void
    {
        $this->makeScenario(['name' => 'Optimistic']);
        $this->makeScenario(['name' => 'Pessimistic']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/scenarios');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_scenarios_excludes_other_org_scenarios(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        CashFlowScenario::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Org Scenario',
            'is_base_case'    => false,
            'created_by'      => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/scenarios');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Scenarios — store
    // -------------------------------------------------------------------------

    public function test_store_scenario_creates_scenario(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/scenarios', [
                'name'         => 'Conservative Growth',
                'description'  => 'Low growth assumption',
                'is_base_case' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Conservative Growth');
    }

    public function test_store_scenario_validates_required_name(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/scenarios', []);

        $response->assertStatus(422);
    }

    public function test_store_scenario_demotes_previous_base_case(): void
    {
        $existing = $this->makeScenario(['is_base_case' => true]);

        $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/scenarios', [
                'name'         => 'New Base',
                'is_base_case' => true,
            ]);

        $this->assertFalse($existing->fresh()->is_base_case);
    }

    // -------------------------------------------------------------------------
    // Scenarios — update
    // -------------------------------------------------------------------------

    public function test_update_scenario_modifies_scenario(): void
    {
        $scenario = $this->makeScenario(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/cash-flow/scenarios/' . $scenario->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Scenarios — destroy
    // -------------------------------------------------------------------------

    public function test_destroy_scenario_soft_deletes(): void
    {
        $scenario = $this->makeScenario();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/cash-flow/scenarios/' . $scenario->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('cash_flow_scenarios', ['id' => $scenario->id]);
    }

    // -------------------------------------------------------------------------
    // Forecasts — index
    // -------------------------------------------------------------------------

    public function test_index_forecasts_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/forecasts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Forecasts — generate
    // -------------------------------------------------------------------------

    public function test_generate_forecast_creates_forecast(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days'  => 30,
                'currency_code' => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.forecast'));
        $this->assertNotNull($response->json('data.period_summary'));
    }

    public function test_generate_forecast_rejects_invalid_horizon(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days' => 45, // not in [30,60,90]
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Forecasts — show and lines
    // -------------------------------------------------------------------------

    public function test_show_forecast_returns_details(): void
    {
        // Generate then show
        $generateRes = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days' => 30,
            ]);
        $generateRes->assertStatus(201);

        $forecastUuid = $generateRes->json('data.forecast.uuid');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/forecasts/' . $forecastUuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.forecast.uuid', $forecastUuid);
    }

    public function test_forecast_lines_returns_paginated_lines(): void
    {
        $generateRes = $this->withToken($this->token)
            ->postJson('/api/v1/cash-flow/forecasts/generate', [
                'horizon_days' => 30,
            ]);
        $generateRes->assertStatus(201);

        $forecastUuid = $generateRes->json('data.forecast.uuid');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cash-flow/forecasts/' . $forecastUuid . '/lines');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/cash-flow/scenarios')->assertStatus(401);
        $this->getJson('/api/v1/cash-flow/forecasts')->assertStatus(401);
    }
}
