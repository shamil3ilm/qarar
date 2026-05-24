<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ProfitCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProfitCenterTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.profit-center.view',
            'accounting.controlling.profit-center.create',
            'accounting.controlling.profit-center.update',
            'accounting.controlling.profit-center.delete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCenter(array $overrides = []): ProfitCenter
    {
        return ProfitCenter::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'PC-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Profit Center',
            'status'          => ProfitCenter::STATUS_ACTIVE,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCenter();
        $this->makeCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_profit_center(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/profit-centers', [
                'code' => 'PC-001',
                'name' => 'Sales Division',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/profit-centers', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_profit_center_details(): void
    {
        $center = $this->makeCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers/' . $center->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $center->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_profit_center(): void
    {
        $center = $this->makeCenter(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/profit-centers/' . $center->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $center->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_profit_center(): void
    {
        $center = $this->makeCenter();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/profit-centers/' . $center->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('profit_centers', ['id' => $center->id]);
    }

    // -------------------------------------------------------------------------
    // Deactivate
    // -------------------------------------------------------------------------

    public function test_deactivate_changes_status(): void
    {
        $center = $this->makeCenter(['status' => ProfitCenter::STATUS_ACTIVE]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/profit-centers/' . $center->uuid . '/deactivate');

        $response->assertStatus(200);
        $this->assertEquals('inactive', $center->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Report
    // -------------------------------------------------------------------------

    public function test_report_all_validates_dates_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers/report');

        $response->assertStatus(422);
    }

    public function test_report_all_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers/report?from=2025-01-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_report_for_center_validates_dates(): void
    {
        $center = $this->makeCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers/' . $center->uuid . '/report');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Plan
    // -------------------------------------------------------------------------

    public function test_set_plan_validates_required_fields(): void
    {
        $center = $this->makeCenter();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/profit-centers/' . $center->uuid . '/plan', []);

        $response->assertStatus(422);
    }

    public function test_set_plan_creates_period_plan(): void
    {
        $center = $this->makeCenter();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/profit-centers/' . $center->uuid . '/plan', [
                'fiscal_year'  => 2025,
                'period'       => 3,
                'plan_revenue' => 100000.00,
                'plan_cost'    => 80000.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_get_plan_validates_fiscal_year_required(): void
    {
        $center = $this->makeCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/profit-centers/' . $center->uuid . '/plan');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/profit-centers')->assertStatus(401);
    }
}
