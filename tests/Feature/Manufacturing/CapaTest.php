<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\CapaRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CapaTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_capas(): void
    {
        CapaRecord::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/capas', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_capa(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capas',
            [
                'capa_number'       => 'CAPA-2026-001',
                'capa_type'         => 'corrective',
                'problem_statement' => 'Defects found on line 3',
                'priority'          => 'high',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('capa_records', [
            'capa_number'     => 'CAPA-2026-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_capa_number(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capas',
            [
                'capa_type'         => 'corrective',
                'problem_statement' => 'Problem',
                'priority'          => 'high',
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_problem_statement(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capas',
            [
                'capa_number' => 'CAPA-2026-002',
                'capa_type'   => 'corrective',
                'priority'    => 'high',
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_capa(): void
    {
        $capa = CapaRecord::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/capas/{$capa->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── addAction ────────────────────────────────────────────────────────────

    public function test_add_action_creates_action(): void
    {
        $capa = CapaRecord::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/capas/{$capa->id}/actions",
            [
                'action_number' => 'ACT-001',
                'description'   => 'Retrain operators',
                'due_date'      => now()->addDays(14)->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('capa_actions', [
            'capa_record_id' => $capa->id,
            'action_number'  => 'ACT-001',
        ]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/capas')->assertUnauthorized();
    }
}
