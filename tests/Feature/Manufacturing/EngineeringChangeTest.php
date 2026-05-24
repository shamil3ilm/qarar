<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\EngineeringChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EngineeringChangeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_changes(): void
    {
        EngineeringChange::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/engineering-changes', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_engineering_change(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/engineering-changes',
            [
                'change_number' => 'ECR-2026-001',
                'description'   => 'Update BOM for product A',
                'change_type'   => 'bom_change',
                'priority'      => 'normal',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('engineering_changes', [
            'change_number'   => 'ECR-2026-001',
            'organization_id' => $this->organization->id,
            'status'          => EngineeringChange::STATUS_DRAFT,
        ]);
    }

    public function test_store_requires_change_number(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/engineering-changes',
            ['description' => 'Some change'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_description(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/engineering-changes',
            ['change_number' => 'ECR-2026-002'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_rejects_duplicate_change_number(): void
    {
        EngineeringChange::factory()->create([
            'organization_id' => $this->organization->id,
            'change_number'   => 'ECR-DUPE-001',
        ]);

        $response = $this->postJson(
            '/api/v1/manufacturing/engineering-changes',
            [
                'change_number' => 'ECR-DUPE-001',
                'description'   => 'Duplicate',
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_engineering_change(): void
    {
        $ec = EngineeringChange::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->getJson(
            "/api/v1/manufacturing/engineering-changes/{$ec->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_description(): void
    {
        $ec = EngineeringChange::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/engineering-changes/{$ec->id}",
            ['description' => 'Updated description'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('engineering_changes', [
            'id'          => $ec->id,
            'description' => 'Updated description',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_engineering_change(): void
    {
        $ec = EngineeringChange::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/engineering-changes/{$ec->id}",
            [],
            $this->authHeaders()
        );

        $response->assertNoContent();
        $this->assertSoftDeleted('engineering_changes', ['id' => $ec->id]);
    }

    // ─── submit ───────────────────────────────────────────────────────────────

    public function test_submit_transitions_to_submitted(): void
    {
        $ec = EngineeringChange::factory()->draft()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/engineering-changes/{$ec->id}/submit",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('engineering_changes', [
            'id'     => $ec->id,
            'status' => EngineeringChange::STATUS_SUBMITTED,
        ]);
    }

    // ─── approve ──────────────────────────────────────────────────────────────

    public function test_approve_transitions_submitted_to_approved(): void
    {
        $ec = EngineeringChange::factory()->submitted()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/engineering-changes/{$ec->id}/approve",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('engineering_changes', [
            'id'     => $ec->id,
            'status' => EngineeringChange::STATUS_APPROVED,
        ]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/engineering-changes')->assertUnauthorized();
    }
}
