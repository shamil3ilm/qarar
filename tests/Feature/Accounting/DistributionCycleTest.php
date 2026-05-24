<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CoDistributionCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DistributionCycleTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.cycle.view',
            'accounting.controlling.cycle.create',
            'accounting.controlling.cycle.update',
            'accounting.controlling.cycle.delete',
            'accounting.controlling.cycle.execute',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCycle(array $overrides = []): CoDistributionCycle
    {
        return CoDistributionCycle::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Distribution Cycle ' . fake()->unique()->numerify('###'),
            'fiscal_year'     => 2025,
            'period_from'     => 1,
            'period_to'       => 12,
            'status'          => CoDistributionCycle::STATUS_OPEN,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/distribution-cycles', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_cycle(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/distribution-cycles', [
                'name'        => 'Overhead Distribution',
                'fiscal_year' => 2025,
                'period_from' => 1,
                'period_to'   => 3,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_open_cycle(): void
    {
        $cycle = $this->makeCycle(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $cycle->fresh()->name);
    }

    public function test_update_rejects_non_open_cycle(): void
    {
        $cycle = $this->makeCycle(['status' => CoDistributionCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_open_cycle(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid);

        $response->assertStatus(200);
        $this->assertNull(CoDistributionCycle::find($cycle->id));
    }

    public function test_destroy_rejects_non_open_cycle(): void
    {
        $cycle = $this->makeCycle(['status' => CoDistributionCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Execute
    // -------------------------------------------------------------------------

    public function test_execute_validates_period_required(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid . '/execute', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Postings
    // -------------------------------------------------------------------------

    public function test_postings_returns_list(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid . '/postings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/distribution-cycles')->assertStatus(401);
    }
}
