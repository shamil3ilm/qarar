<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\KanbanSupplyArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class KanbanTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        $this->warehouse = Warehouse::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── supply areas index ───────────────────────────────────────────────────

    public function test_index_supply_areas_returns_paginated(): void
    {
        KanbanSupplyArea::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'warehouse_id'    => $this->warehouse->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/kanban/supply-areas', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── supply areas store ───────────────────────────────────────────────────

    public function test_store_supply_area_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/kanban/supply-areas',
            [
                'code'         => 'KSA-001',
                'name'         => 'Assembly Supply Area',
                'warehouse_id' => $this->warehouse->id,
            ],
            $this->authHeaders()
        );

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('kanban_supply_areas', [
            'code'            => 'KSA-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_supply_area_requires_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/kanban/supply-areas',
            ['name' => 'Test Area', 'warehouse_id' => $this->warehouse->id],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── control cycles index ─────────────────────────────────────────────────

    public function test_index_control_cycles_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/kanban/control-cycles', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── gantt / board ────────────────────────────────────────────────────────

    public function test_board_returns_data(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/kanban/board', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/kanban/supply-areas')->assertUnauthorized();
    }
}
