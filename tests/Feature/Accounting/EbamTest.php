<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankAccountRequest;
use App\Models\Accounting\BankSignatory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EbamTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.bank-accounts.view',
            'accounting.bank-accounts.manage',
            'accounting.bank-accounts.approve',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeBankAccount(): BankAccount
    {
        $glAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'sub_type'        => 'bank',
        ]);

        return BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => 'current',
            'gl_account_id'   => $glAccount->id,
        ]);
    }

    private function makeSignatory(BankAccount $bankAccount, array $overrides = []): BankSignatory
    {
        return BankSignatory::create(array_merge([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $bankAccount->id,
            'name'            => 'Test Signatory',
            'authority_level' => 'single',
            'valid_from'      => '2025-01-01',
            'is_active'       => true,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    private function makeRequest(array $overrides = []): BankAccountRequest
    {
        return BankAccountRequest::create(array_merge([
            'organization_id' => $this->organization->id,
            'request_type'    => 'open',
            'status'          => 'pending',
            'bank_name'       => 'Test Bank',
            'account_name'    => 'Test Account',
            'currency_code'   => 'SAR',
            'requested_by'    => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Signatories
    // -------------------------------------------------------------------------

    public function test_signatories_returns_list_for_bank_account(): void
    {
        $bankAccount = $this->makeBankAccount();
        $this->makeSignatory($bankAccount);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-accounts/' . $bankAccount->uuid . '/signatories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_add_signatory_creates_signatory(): void
    {
        $bankAccount = $this->makeBankAccount();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-accounts/' . $bankAccount->uuid . '/signatories', [
                'name'            => 'New Signatory',
                'authority_level' => 'single',
                'valid_from'      => '2025-01-01',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_add_signatory_validates_required_fields(): void
    {
        $bankAccount = $this->makeBankAccount();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-accounts/' . $bankAccount->uuid . '/signatories', []);

        $response->assertStatus(422);
    }

    public function test_revoke_signatory_revokes_active_signatory(): void
    {
        $bankAccount = $this->makeBankAccount();
        $signatory   = $this->makeSignatory($bankAccount);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-accounts/' . $bankAccount->uuid . '/signatories/' . $signatory->uuid . '/revoke', [
                'reason' => 'Signatory left the company.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertFalse($signatory->fresh()->is_active);
    }

    // -------------------------------------------------------------------------
    // Bank Account Requests — Index / Show
    // -------------------------------------------------------------------------

    public function test_requests_returns_paginated_list(): void
    {
        $this->makeRequest();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-account-requests');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_request_returns_details(): void
    {
        $req = $this->makeRequest();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-account-requests/' . $req->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $req->id);
    }

    public function test_show_request_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/bank-account-requests/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Bank Account Requests — Create
    // -------------------------------------------------------------------------

    public function test_create_request_creates_open_request(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-account-requests', [
                'request_type' => 'open',
                'bank_name'    => 'ABC Bank',
                'account_name' => 'Operating Account',
                'currency_code' => 'SAR',
                'justification' => 'New project account',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_create_request_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-account-requests', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Review Request
    // -------------------------------------------------------------------------

    public function test_review_request_approves_pending_request(): void
    {
        $req = $this->makeRequest(['status' => 'pending']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-account-requests/' . $req->uuid . '/review', [
                'action'         => 'approve',
                'notes'          => 'Looks good.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals('approved', $req->fresh()->status);
    }

    public function test_review_request_rejects_pending_request(): void
    {
        $req = $this->makeRequest(['status' => 'pending']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/bank-account-requests/' . $req->uuid . '/review', [
                'action'         => 'reject',
                'reason'         => 'Not needed.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals('rejected', $req->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/bank-account-requests')->assertStatus(401);
    }
}
