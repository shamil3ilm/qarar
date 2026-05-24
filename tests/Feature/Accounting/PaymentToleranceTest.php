<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\PaymentToleranceGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentToleranceTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.tolerance.view',
            'accounting.tolerance.manage',
            'accounting.tolerance.post',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGroup(array $overrides = []): PaymentToleranceGroup
    {
        return PaymentToleranceGroup::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'TOL-' . fake()->unique()->numerify('###'),
            'name'            => 'Standard Tolerance',
            'applies_to'      => 'both',
            'is_active'       => true,
            'is_default'      => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index Groups
    // -------------------------------------------------------------------------

    public function test_index_groups_returns_paginated_list(): void
    {
        $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-tolerance/groups');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_index_groups_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-tolerance/groups');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store Group
    // -------------------------------------------------------------------------

    public function test_store_group_creates_tolerance_group(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-tolerance/groups', [
                'code'       => 'CUST-TOL',
                'name'       => 'Customer Tolerance',
                'applies_to' => 'customer',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_group_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-tolerance/groups', []);

        $response->assertStatus(422);
    }

    public function test_store_group_validates_applies_to_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-tolerance/groups', [
                'code'       => 'XX',
                'name'       => 'Test',
                'applies_to' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show Group
    // -------------------------------------------------------------------------

    public function test_show_group_returns_details(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-tolerance/groups/' . $group->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $group->id);
    }

    public function test_show_group_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-tolerance/groups/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update Group
    // -------------------------------------------------------------------------

    public function test_update_group_modifies_tolerance_group(): void
    {
        $group = $this->makeGroup(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/payment-tolerance/groups/' . $group->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $group->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Upsert Item
    // -------------------------------------------------------------------------

    public function test_upsert_item_validates_required_fields(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-tolerance/groups/' . $group->uuid . '/items', []);

        $response->assertStatus(422);
    }

    public function test_upsert_item_creates_tolerance_item(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-tolerance/groups/' . $group->uuid . '/items', [
                'currency_code' => 'SAR',
                'underpay_abs'  => 5.00,
                'overpay_abs'   => 5.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Evaluate
    // -------------------------------------------------------------------------

    public function test_evaluate_validates_required_fields(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-tolerance/groups/' . $group->uuid . '/evaluate', []);

        $response->assertStatus(422);
    }

    public function test_evaluate_returns_tolerance_result(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-tolerance/groups/' . $group->uuid . '/evaluate', [
                'invoice_amount' => 1000.00,
                'payment_amount' => 995.00,
                'currency_code'  => 'SAR',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Variance Summary
    // -------------------------------------------------------------------------

    public function test_variance_summary_validates_required_dates(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-tolerance/variance-summary');

        $response->assertStatus(422);
    }

    public function test_variance_summary_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-tolerance/variance-summary?from=2025-01-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Difference Posts
    // -------------------------------------------------------------------------

    public function test_difference_posts_returns_paginated_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-tolerance/difference-posts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/payment-tolerance/groups')->assertStatus(401);
    }
}
