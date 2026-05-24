<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\InterCompanyTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class InterCompanyTransferTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.transfers.view',
            'accounting.transfers.create',
            'accounting.transfers.approve',
            'accounting.transfers.complete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTransfer(array $overrides = []): InterCompanyTransfer
    {
        return InterCompanyTransfer::create(array_merge([
            'organization_id' => $this->organization->id,
            'uuid'            => fake()->uuid(),
            'transfer_number' => 'ICT-' . fake()->unique()->numerify('######'),
            'transfer_type'   => 'fund_transfer',
            'amount'          => 5000.00,
            'currency_code'   => 'SAR',
            'transfer_date'   => '2025-01-31',
            'status'          => 'pending',
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeTransfer();
        $this->makeTransfer();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/loans/inter-company-transfers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/loans/inter-company-transfers');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_transfer(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/loans/inter-company-transfers', [
                'transfer_type' => 'fund_transfer',
                'amount'        => 10000,
                'currency_code' => 'SAR',
                'transfer_date' => '2025-01-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/loans/inter-company-transfers', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_transfer_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/loans/inter-company-transfers', [
                'transfer_type' => 'invalid',
                'amount'        => 100,
                'transfer_date' => '2025-01-31',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_transfer_details(): void
    {
        $transfer = $this->makeTransfer();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/loans/inter-company-transfers/' . $transfer->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $transfer->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/loans/inter-company-transfers/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Review (approve / reject)
    // -------------------------------------------------------------------------

    public function test_review_approves_pending_transfer(): void
    {
        $transfer = $this->makeTransfer(['status' => 'pending']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/loans/inter-company-transfers/' . $transfer->id . '/review', [
                'action' => 'approve',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('approved', $transfer->fresh()->status);
    }

    public function test_review_rejects_pending_transfer(): void
    {
        $transfer = $this->makeTransfer(['status' => 'pending']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/loans/inter-company-transfers/' . $transfer->id . '/review', [
                'action' => 'reject',
                'reason' => 'Insufficient funds.',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $transfer->fresh()->status);
    }

    public function test_review_validates_action_enum(): void
    {
        $transfer = $this->makeTransfer();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/loans/inter-company-transfers/' . $transfer->id . '/review', [
                'action' => 'invalid_action',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Complete
    // -------------------------------------------------------------------------

    public function test_complete_marks_approved_transfer_as_completed(): void
    {
        $transfer = $this->makeTransfer(['status' => 'approved']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/loans/inter-company-transfers/' . $transfer->id . '/complete');

        $response->assertStatus(200);
        $this->assertEquals('completed', $transfer->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/loans/inter-company-transfers')->assertStatus(401);
    }
}
