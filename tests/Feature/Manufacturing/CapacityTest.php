<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CapacityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.capacity.view',
            'manufacturing.capacity.create',
            'manufacturing.capacity.edit',
            'manufacturing.capacity.delete',
        ]);
    }

    // ─── work centers ─────────────────────────────────────────────────────────

    public function test_index_work_centers_returns_paginated(): void
    {
        WorkCenter::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/capacity/work-centers', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_work_center_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capacity/work-centers',
            [
                'code'             => 'WC-TEST-01',
                'name'             => 'Assembly Line 1',
                'work_center_type' => 'assembly',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_centers', [
            'code'            => 'WC-TEST-01',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_work_center_requires_code(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capacity/work-centers',
            ['name' => 'Assembly Line'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_work_center_requires_name(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/capacity/work-centers',
            ['code' => 'WC-002'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_show_work_center(): void
    {
        $wc = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/capacity/work-centers/{$wc->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_update_work_center(): void
    {
        $wc = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/capacity/work-centers/{$wc->id}",
            ['name' => 'Updated Center Name'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('work_centers', ['id' => $wc->id, 'name' => 'Updated Center Name']);
    }

    public function test_destroy_work_center_soft_deletes(): void
    {
        $wc = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/capacity/work-centers/{$wc->id}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('work_centers', ['id' => $wc->id]);
    }

    // ─── reporting ────────────────────────────────────────────────────────────

    public function test_capacity_load_returns_data(): void
    {
        $from = now()->subMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $response = $this->getJson(
            "/api/v1/manufacturing/capacity/load?from={$from}&to={$to}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_bottlenecks_returns_data(): void
    {
        $from = now()->subMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $response = $this->getJson(
            "/api/v1/manufacturing/capacity/bottlenecks?from={$from}&to={$to}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/capacity/work-centers')->assertUnauthorized();
    }
}
