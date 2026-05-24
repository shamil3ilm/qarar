<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\SpecialLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SpecialLedgerTest extends TestCase
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

    private function makeLedger(array $overrides = []): SpecialLedger
    {
        return SpecialLedger::create(array_merge([
            'organization_id'      => $this->organization->id,
            'code'                 => 'SL-' . fake()->unique()->numerify('##'),
            'name'                 => 'IFRS Special Ledger',
            'accounting_principle' => 'ifrs',
            'is_leading'           => false,
            'is_active'            => true,
            'currency_code'        => 'SAR',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_ledger_list(): void
    {
        $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_special_ledger(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/special-ledgers', [
                'code'                 => 'GAAP',
                'name'                 => 'GAAP Ledger',
                'accounting_principle' => 'gaap',
                'currency_code'        => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/special-ledgers', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_accounting_principle_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/special-ledgers', [
                'code'                 => 'XX',
                'name'                 => 'Test',
                'accounting_principle' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_ledger_details(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $ledger->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_ledger(): void
    {
        $ledger = $this->makeLedger(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/special-ledgers/' . $ledger->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $ledger->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_non_leading_ledger(): void
    {
        $ledger = $this->makeLedger(['is_leading' => false]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/special-ledgers/' . $ledger->id);

        $response->assertStatus(200);
        $this->assertNull(SpecialLedger::find($ledger->id));
    }

    public function test_destroy_rejects_leading_ledger(): void
    {
        $ledger = $this->makeLedger(['is_leading' => true]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/special-ledgers/' . $ledger->id);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Trial Balance
    // -------------------------------------------------------------------------

    public function test_trial_balance_validates_fiscal_year_required(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/trial-balance');

        $response->assertStatus(422);
    }

    public function test_trial_balance_returns_data(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/trial-balance?fiscal_year=2025&period=3');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Entries
    // -------------------------------------------------------------------------

    public function test_entries_returns_paginated_list(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/entries');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/special-ledgers')->assertStatus(401);
    }
}
