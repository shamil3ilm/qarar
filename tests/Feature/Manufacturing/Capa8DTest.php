<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\CapaEightD;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class Capa8DTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_records(): void
    {
        CapaEightD::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/capa-8d', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_record(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capa-8d',
            ['title' => 'Customer defect investigation'],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('qm_capa_8d', [
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_title(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capa-8d',
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_record(): void
    {
        $record = CapaEightD::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/capa-8d/{$record->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── updateStep ───────────────────────────────────────────────────────────

    public function test_update_step_d0(): void
    {
        $record = CapaEightD::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/capa-8d/{$record->uuid}/steps/d0",
            [
                'd0_emergency_response' => 'Quarantine affected batch',
                'd0_date'               => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('qm_capa_8d', [
            'id'                    => $record->id,
            'd0_emergency_response' => 'Quarantine affected batch',
        ]);
    }

    // ─── close ────────────────────────────────────────────────────────────────

    public function test_close_marks_record_as_closed(): void
    {
        $record = CapaEightD::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CapaEightD::STATUS_D7_PREVENTION,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/capa-8d/{$record->uuid}/close",
            ['d8_recognition' => 'Team recognized for excellent work'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('qm_capa_8d', [
            'id'     => $record->id,
            'status' => CapaEightD::STATUS_D8_CLOSED,
        ]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/capa-8d')->assertUnauthorized();
    }
}
