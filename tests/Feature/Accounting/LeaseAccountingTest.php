<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\LeaseContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class LeaseAccountingTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.leases.view',
            'accounting.leases.create',
            'accounting.leases.manage',
            'accounting.leases.post',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLease(array $overrides = []): LeaseContract
    {
        return LeaseContract::create(array_merge([
            'organization_id'   => $this->organization->id,
            'lease_number'      => 'LEASE-' . fake()->unique()->numerify('######'),
            'party_role'        => 'lessee',
            'asset_description' => 'Office building lease',
            'commencement_date' => '2025-01-01',
            'end_date'          => '2026-12-31',
            'lease_term_months' => 24,
            'payment_amount'    => 10000.00,
            'payment_frequency' => 'monthly',
            'currency_code'     => 'SAR',
            'discount_rate'     => 0.05,
            'classification'    => 'finance',
            'status'            => 'active',
            'created_by'        => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeLease();
        $this->makeLease();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/leases');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/leases');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_lease_with_schedule(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/leases', [
                'asset_description' => 'Warehouse lease',
                'commencement_date' => '2025-01-01',
                'end_date'          => '2025-12-31',
                'lease_term_months' => 12,
                'payment_amount'    => 5000,
                'payment_frequency' => 'monthly',
                'discount_rate'     => 0.05,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/leases', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_classification_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/leases', [
                'asset_description' => 'Test',
                'commencement_date' => '2025-01-01',
                'end_date'          => '2025-12-31',
                'lease_term_months' => 12,
                'payment_amount'    => 5000,
                'discount_rate'     => 0.05,
                'classification'    => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_lease_details(): void
    {
        $lease = $this->makeLease();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/leases/' . $lease->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $lease->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/leases/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Schedule
    // -------------------------------------------------------------------------

    public function test_schedule_returns_amortisation_schedule(): void
    {
        $lease = $this->makeLease();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/leases/' . $lease->uuid . '/schedule');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Post Period Entry
    // -------------------------------------------------------------------------

    public function test_post_entry_validates_period_number(): void
    {
        $lease = $this->makeLease();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/leases/' . $lease->uuid . '/post-entry', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Terminate
    // -------------------------------------------------------------------------

    public function test_terminate_validates_required_date(): void
    {
        $lease = $this->makeLease();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/leases/' . $lease->uuid . '/terminate', []);

        $response->assertStatus(422);
    }

    public function test_terminate_marks_lease_as_terminated(): void
    {
        $lease = $this->makeLease(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/leases/' . $lease->uuid . '/terminate', [
                'termination_date' => '2025-06-30',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('terminated', $lease->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Modify
    // -------------------------------------------------------------------------

    public function test_modify_remeasures_lease(): void
    {
        $lease = $this->makeLease(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/leases/' . $lease->uuid . '/modify', [
                'payment_amount' => 12000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/leases')->assertStatus(401);
    }
}
