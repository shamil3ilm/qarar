<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\PaymentRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentRunTest extends TestCase
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

    private function makeRun(array $overrides = []): PaymentRun
    {
        return PaymentRun::create(array_merge([
            'organization_id'   => $this->organization->id,
            'run_reference'     => 'RUN-' . fake()->unique()->numerify('######'),
            'payment_direction' => 'outgoing',
            'payment_date'      => '2025-01-31',
            'status'            => PaymentRun::STATUS_DRAFT,
            'created_by'        => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeRun();
        $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-runs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-runs');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_proposes_payment_run(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-runs', [
                'run_reference' => 'RUN-2025-001',
                'payment_date'  => '2025-01-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-runs', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_run_details(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-runs/' . $run->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $run->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-runs/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_draft_run(): void
    {
        $run = $this->makeRun(['status' => PaymentRun::STATUS_DRAFT]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/payment-runs/' . $run->uuid, [
                'payment_date' => '2025-02-28',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('2025-02-28', $run->fresh()->payment_date->toDateString());
    }

    public function test_update_rejects_non_draft_run(): void
    {
        $run = $this->makeRun(['status' => 'proposed']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/payment-runs/' . $run->uuid, [
                'payment_date' => '2025-02-28',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public function test_cancel_transitions_draft_run(): void
    {
        $run = $this->makeRun(['status' => PaymentRun::STATUS_DRAFT]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-runs/' . $run->uuid . '/cancel');

        $response->assertStatus(200);
        $this->assertEquals(PaymentRun::STATUS_CANCELLED, $run->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_draft_run(): void
    {
        $run = $this->makeRun(['status' => PaymentRun::STATUS_DRAFT]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/payment-runs/' . $run->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('payment_runs', ['id' => $run->id]);
    }

    public function test_destroy_rejects_posted_run(): void
    {
        $run = $this->makeRun(['status' => 'posted']);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/payment-runs/' . $run->uuid);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/payment-runs')->assertStatus(401);
    }
}
