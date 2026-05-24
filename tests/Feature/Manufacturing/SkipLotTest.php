<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\SkipLotSamplingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SkipLotTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_plans(): void
    {
        SkipLotSamplingPlan::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/skip-lot-plans', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_plan(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/skip-lot-plans',
            [
                'plan_code'            => 'SLP-001',
                'plan_name'            => 'Standard Skip Lot',
                'plan_type'            => 'skip_lot',
                'inspection_frequency' => 5,
                'sample_size_percent'  => 20,
                'accept_number'        => 0,
                'reject_number'        => 1,
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('skip_lot_sampling_plans', [
            'plan_code'       => 'SLP-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_plan_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/skip-lot-plans',
            ['plan_name' => 'Test', 'plan_type' => 'normal'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_plan_type(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/skip-lot-plans',
            ['plan_code' => 'SLP-002', 'plan_name' => 'Test'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_plan(): void
    {
        $plan = SkipLotSamplingPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/skip-lot-plans/{$plan->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_plan_name(): void
    {
        $plan = SkipLotSamplingPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/skip-lot-plans/{$plan->id}",
            ['plan_name' => 'Updated Plan Name'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('skip_lot_sampling_plans', [
            'id'        => $plan->id,
            'plan_name' => 'Updated Plan Name',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_plan(): void
    {
        $plan = SkipLotSamplingPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/skip-lot-plans/{$plan->id}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('skip_lot_sampling_plans', ['id' => $plan->id]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/skip-lot-plans')->assertUnauthorized();
    }
}
