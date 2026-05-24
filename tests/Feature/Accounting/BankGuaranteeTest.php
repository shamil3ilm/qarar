<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\BankGuarantee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BankGuaranteeTest extends TestCase
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

    private function makeGuarantee(array $overrides = []): BankGuarantee
    {
        return BankGuarantee::create(array_merge([
            'organization_id'  => $this->organization->id,
            'guarantee_number' => 'BG-' . fake()->unique()->numerify('####'),
            'guarantee_type'   => 'performance_bond',
            'direction'        => 'issued',
            'currency_code'    => 'SAR',
            'amount'           => 50000.00,
            'issue_date'       => now()->toDateString(),
            'status'           => 'draft',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_guarantees(): void
    {
        $this->makeGuarantee();
        $this->makeGuarantee(['guarantee_number' => 'BG-0002']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-guarantees');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makeGuarantee(['status' => 'draft']);
        $this->makeGuarantee(['guarantee_number' => 'BG-ACTIVE', 'status' => 'active']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-guarantees?status=draft');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_direction(): void
    {
        $this->makeGuarantee(['direction' => 'issued']);
        $this->makeGuarantee(['guarantee_number' => 'BG-RCV', 'direction' => 'received']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-guarantees?direction=received');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_guarantee(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-guarantees', [
                'guarantee_number' => 'BG-TEST-001',
                'guarantee_type'   => 'bid_bond',
                'amount'           => 10000.00,
                'issue_date'       => '2026-01-01',
                'expiry_date'      => '2026-06-30',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.guarantee_number', 'BG-TEST-001')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-guarantees', []);

        $response->assertStatus(422);
    }

    public function test_store_rejects_expiry_before_issue_date(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-guarantees', [
                'guarantee_number' => 'BG-BAD',
                'amount'           => 1000.00,
                'issue_date'       => '2026-06-01',
                'expiry_date'      => '2026-01-01', // before issue_date
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_guarantee(): void
    {
        $guarantee = $this->makeGuarantee();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-guarantees/' . $guarantee->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $guarantee->id);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-guarantees/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_draft_guarantee(): void
    {
        $guarantee = $this->makeGuarantee(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/bank-guarantees/' . $guarantee->id, [
                'amount' => 75000.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.amount', '75000.0000');
    }

    public function test_update_rejects_claimed_guarantee(): void
    {
        $guarantee = $this->makeGuarantee(['status' => 'claimed']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/bank-guarantees/' . $guarantee->id, [
                'amount' => 75000.00,
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_guarantee(): void
    {
        $guarantee = $this->makeGuarantee();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/bank-guarantees/' . $guarantee->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('bank_guarantees', ['id' => $guarantee->id]);
    }

    // -------------------------------------------------------------------------
    // Activate
    // -------------------------------------------------------------------------

    public function test_activate_transitions_draft_to_active(): void
    {
        $guarantee = $this->makeGuarantee(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-guarantees/' . $guarantee->id . '/activate');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_activate_rejects_already_active_guarantee(): void
    {
        $guarantee = $this->makeGuarantee(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-guarantees/' . $guarantee->id . '/activate');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Return
    // -------------------------------------------------------------------------

    public function test_return_transitions_active_guarantee(): void
    {
        $guarantee = $this->makeGuarantee(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-guarantees/' . $guarantee->id . '/return');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'returned');
    }

    // -------------------------------------------------------------------------
    // Expiring soon
    // -------------------------------------------------------------------------

    public function test_expiring_soon_returns_active_guarantees_near_expiry(): void
    {
        $this->makeGuarantee([
            'status'      => 'active',
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->makeGuarantee([
            'guarantee_number' => 'BG-FAR',
            'status'           => 'active',
            'expiry_date'      => now()->addDays(90)->toDateString(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-guarantees/expiring-soon?days=30');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/bank-guarantees')->assertStatus(401);
    }
}
