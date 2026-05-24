<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ActivityType;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ActivityTypeTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeActivityType(array $overrides = []): ActivityType
    {
        return ActivityType::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'AT-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Activity Type',
            'unit_of_measure' => 'hours',
            'is_active'       => true,
        ], $overrides));
    }

    private function makeCostCenter(): CostCenter
    {
        return CostCenter::create([
            'organization_id' => $this->organization->id,
            'code'            => 'CC-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Cost Center',
            'status'          => 'active',
        ]);
    }

    private function makeFiscalYear(): FiscalYear
    {
        return FiscalYear::create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'is_closed'       => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeActivityType();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/activity-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/activity-types', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_activity_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/activity-types', [
                'code'            => 'MACH-001',
                'name'            => 'Machine Hours',
                'unit_of_measure' => 'hours',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $activityType = $this->makeActivityType();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/activity-types/' . $activityType->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/activity-types/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_activity_type(): void
    {
        $activityType = $this->makeActivityType(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/activity-types/' . $activityType->uuid, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $activityType->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_activity_type(): void
    {
        $activityType = $this->makeActivityType();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/activity-types/' . $activityType->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Set Rate
    // -------------------------------------------------------------------------

    public function test_set_rate_validates_required_fields(): void
    {
        $activityType = $this->makeActivityType();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/activity-types/' . $activityType->uuid . '/rates', []);

        $response->assertStatus(422);
    }

    public function test_set_rate_creates_or_updates_rate(): void
    {
        $activityType = $this->makeActivityType();
        $costCenter   = $this->makeCostCenter();
        $fiscalYear   = $this->makeFiscalYear();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/activity-types/' . $activityType->uuid . '/rates', [
                'cost_center_id' => $costCenter->id,
                'fiscal_year_id' => $fiscalYear->id,
                'period'         => 3,
                'planned_rate'   => 50.00,
                'currency_code'  => 'SAR',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/activity-types')->assertStatus(401);
    }
}
