<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\CalibrationEquipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CalibrationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── equipment ────────────────────────────────────────────────────────────

    public function test_index_equipment_returns_paginated(): void
    {
        CalibrationEquipment::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/calibration/equipment', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_equipment_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/calibration/equipment',
            [
                'equipment_code' => 'CAL-001',
                'name'           => 'Pressure Gauge A',
                'category'       => 'scale',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('calibration_equipment', [
            'equipment_code'  => 'CAL-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_equipment_requires_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/calibration/equipment',
            ['name' => 'Gauge'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── plans ────────────────────────────────────────────────────────────────

    public function test_index_plans_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/calibration/plans', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── orders ───────────────────────────────────────────────────────────────

    public function test_index_orders_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/calibration/orders', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── overdue / upcoming ───────────────────────────────────────────────────

    public function test_overdue_returns_data(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/calibration/overdue', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/calibration/equipment')->assertUnauthorized();
    }
}
