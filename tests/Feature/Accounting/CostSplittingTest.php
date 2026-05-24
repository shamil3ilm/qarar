<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CostCenter;
use App\Models\Accounting\CostSplittingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CostSplittingTest extends TestCase
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

    private function makeCostCenter(): CostCenter
    {
        return CostCenter::create([
            'organization_id' => $this->organization->id,
            'code'            => 'CC-' . fake()->unique()->numerify('####'),
            'name'            => 'Test Cost Center',
            'status'          => 'active',
        ]);
    }

    private function makeRule(array $overrides = []): CostSplittingRule
    {
        $cc = $this->makeCostCenter();

        return CostSplittingRule::create(array_merge([
            'organization_id'     => $this->organization->id,
            'cost_center_id'      => $cc->id,
            'fixed_percentage'    => 60.00,
            'variable_percentage' => 40.00,
            'splitting_basis'     => CostSplittingRule::BASIS_MANUAL,
            'is_active'           => true,
            'valid_from'          => '2025-01-01',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_rules(): void
    {
        $this->makeRule();
        $this->makeRule();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-splitting/rules');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-splitting/rules');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_splitting_rule(): void
    {
        $cc = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cost-splitting/rules', [
                'cost_center_id'      => $cc->id,
                'fixed_percentage'    => 70,
                'variable_percentage' => 30,
                'valid_from'          => '2025-01-01',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cost-splitting/rules', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_rule_details(): void
    {
        $rule = $this->makeRule();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-splitting/rules/' . $rule->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $rule->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-splitting/rules/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_rule(): void
    {
        $rule = $this->makeRule(['fixed_percentage' => 60]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/cost-splitting/rules/' . $rule->id, [
                'fixed_percentage'    => 50,
                'variable_percentage' => 50,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(50, $rule->fresh()->fixed_percentage);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_rule(): void
    {
        $rule = $this->makeRule();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/cost-splitting/rules/' . $rule->id);

        $response->assertStatus(204);
    }

    // -------------------------------------------------------------------------
    // Run + Results
    // -------------------------------------------------------------------------

    public function test_run_splitting_executes(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cost-splitting/run', [
                'period'      => 1,
                'fiscal_year' => 2025,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_run_splitting_validates_period(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cost-splitting/run', [
                'period'      => 13, // > 12
                'fiscal_year' => 2025,
            ]);

        $response->assertStatus(422);
    }

    public function test_results_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/cost-splitting/results?period=1&fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/cost-splitting/rules')->assertStatus(401);
    }
}
