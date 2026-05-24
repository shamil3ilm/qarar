<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\AuditPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AuditManagementTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_plans(): void
    {
        AuditPlan::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/audit-plans', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_audit_plan(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/audit-plans',
            [
                'plan_number'   => 'AUD-2026-001',
                'title'         => 'ISO 9001 Internal Audit',
                'audit_type'    => 'internal',
                'planned_start' => now()->addDays(7)->format('Y-m-d'),
                'planned_end'   => now()->addDays(8)->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('audit_plans', [
            'plan_number'     => 'AUD-2026-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_plan_number(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/audit-plans',
            [
                'title'         => 'Audit',
                'audit_type'    => 'internal',
                'planned_start' => now()->addDays(7)->format('Y-m-d'),
                'planned_end'   => now()->addDays(8)->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_dates(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/audit-plans',
            [
                'plan_number' => 'AUD-2026-002',
                'title'       => 'Audit',
                'audit_type'  => 'internal',
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_audit_plan(): void
    {
        $plan = AuditPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/audit-plans/{$plan->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── addChecklist ─────────────────────────────────────────────────────────

    public function test_add_checklist_item(): void
    {
        $plan = AuditPlan::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/audit-plans/{$plan->id}/checklists",
            [
                'item_number' => '1.1',
                'question'    => 'Are quality procedures documented?',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('audit_checklists', [
            'audit_plan_id' => $plan->id,
            'item_number'   => '1.1',
        ]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/audit-plans')->assertUnauthorized();
    }
}
