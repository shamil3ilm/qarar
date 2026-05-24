<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Finance\PettyCashFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PettyCashTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.petty-cash.view',
            'accounting.petty-cash.manage',
            'accounting.petty-cash.create',
            'accounting.petty-cash.approve',
            'accounting.petty-cash.post',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAccount(): Account
    {
        return Account::create([
            'organization_id' => $this->organization->id,
            'code'            => 'PCH-' . fake()->unique()->numerify('####'),
            'name'            => 'Petty Cash Account',
            'account_type'    => 'asset',
            'sub_type'        => 'cash',
        ]);
    }

    private function makeFund(array $overrides = []): PettyCashFund
    {
        $account = $this->makeAccount();

        return PettyCashFund::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Main Petty Cash',
            'custodian_id'    => $this->user->id,
            'account_id'      => $account->id,
            'opening_balance' => 1000.00,
            'current_balance' => 1000.00,
            'currency_code'   => 'SAR',
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index Funds
    // -------------------------------------------------------------------------

    public function test_index_funds_returns_paginated_list(): void
    {
        $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_index_funds_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store Fund
    // -------------------------------------------------------------------------

    public function test_store_fund_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show Fund
    // -------------------------------------------------------------------------

    public function test_show_fund_returns_details(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . $fund->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $fund->id);
    }

    public function test_show_fund_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update Fund
    // -------------------------------------------------------------------------

    public function test_update_fund_modifies_fund(): void
    {
        $fund = $this->makeFund(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/petty-cash/funds/' . $fund->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $fund->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Vouchers
    // -------------------------------------------------------------------------

    public function test_index_vouchers_returns_empty_list(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_store_voucher_validates_required_fields(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers', []);

        $response->assertStatus(422);
    }

    public function test_store_voucher_validates_transaction_type_enum(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers', [
                'transaction_type' => 'invalid',
                'amount'           => 100.00,
                'description'      => 'Test',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Replenishments
    // -------------------------------------------------------------------------

    public function test_index_replenishments_returns_empty_list(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/replenishments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_request_replenishment_validates_required_fields(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/replenishments', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/petty-cash/funds')->assertStatus(401);
    }
}
