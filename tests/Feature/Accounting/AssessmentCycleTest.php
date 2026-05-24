<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CoAssessmentCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AssessmentCycleTest extends TestCase
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

    private function makeCycle(array $overrides = []): CoAssessmentCycle
    {
        return CoAssessmentCycle::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Q1 Overhead Assessment',
            'cycle_type'      => CoAssessmentCycle::TYPE_ASSESSMENT,
            'fiscal_year'     => 2026,
            'period_from'     => 1,
            'period_to'       => 3,
            'status'          => CoAssessmentCycle::STATUS_OPEN,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCycle(['name' => 'Cycle A']);
        $this->makeCycle(['name' => 'Cycle B']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/assessment-cycles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_fiscal_year(): void
    {
        $this->makeCycle(['fiscal_year' => 2026]);
        $this->makeCycle(['name' => 'Old Cycle', 'fiscal_year' => 2025]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/assessment-cycles?fiscal_year=2026');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeCycle(['status' => CoAssessmentCycle::STATUS_OPEN]);
        $this->makeCycle(['name' => 'Executed', 'status' => CoAssessmentCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/assessment-cycles?status=open');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_only_returns_own_organization_cycles(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        CoAssessmentCycle::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Org Cycle',
            'cycle_type'      => CoAssessmentCycle::TYPE_ASSESSMENT,
            'fiscal_year'     => 2026,
            'period_from'     => 1,
            'period_to'       => 3,
            'status'          => CoAssessmentCycle::STATUS_OPEN,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/assessment-cycles');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_cycle(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/assessment-cycles', [
                'name'        => 'New Cycle',
                'cycle_type'  => 'assessment',
                'fiscal_year' => 2026,
                'period_from' => 1,
                'period_to'   => 6,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Cycle')
            ->assertJsonPath('data.status', CoAssessmentCycle::STATUS_OPEN);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/assessment-cycles', []);

        $response->assertStatus(422);
    }

    public function test_store_rejects_period_to_before_period_from(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/assessment-cycles', [
                'name'        => 'Bad Periods',
                'fiscal_year' => 2026,
                'period_from' => 6,
                'period_to'   => 3,
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_cycle(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $cycle->id);
    }

    public function test_show_returns_404_for_unknown_cycle(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/assessment-cycles/nonexistent-uuid');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_open_cycle(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_update_rejects_non_open_cycle(): void
    {
        $cycle = $this->makeCycle(['status' => CoAssessmentCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid, [
                'name' => 'Should Fail',
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
            ->deleteJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('co_assessment_cycles', ['id' => $cycle->id]);
    }

    public function test_destroy_rejects_non_open_cycle(): void
    {
        $cycle = $this->makeCycle(['status' => CoAssessmentCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Execute — validation only (no segments means no postings)
    // -------------------------------------------------------------------------

    public function test_execute_rejects_out_of_range_period(): void
    {
        $cycle = $this->makeCycle(['period_from' => 1, 'period_to' => 3]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid . '/execute', [
                'period' => 6,
            ]);

        $response->assertStatus(422);
    }

    public function test_execute_rejects_non_open_cycle(): void
    {
        $cycle = $this->makeCycle(['status' => CoAssessmentCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid . '/execute', [
                'period' => 1,
            ]);

        $response->assertStatus(422);
    }

    public function test_execute_returns_zero_postings_when_no_segments(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid . '/execute', [
                'period' => 1,
            ]);

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.postings_created'));
    }

    // -------------------------------------------------------------------------
    // Postings listing
    // -------------------------------------------------------------------------

    public function test_postings_returns_empty_list_for_new_cycle(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/assessment-cycles/' . $cycle->uuid . '/postings');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/assessment-cycles')->assertStatus(401);
    }
}
