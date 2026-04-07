<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'accounting.bank-reconciliation.view',
            'accounting.bank-reconciliation.create',
            'accounting.bank-reconciliation.update',
            'accounting.bank-reconciliation.complete',
            'accounting.bank-reconciliation.import',
        ]);
        $this->setUpOpenFiscalPeriod();

        // bank_accounts.gl_account_id is NOT NULL — provide a real Account record
        // chart_of_accounts.sub_type is also NOT NULL
        $glAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => 'asset',
            'sub_type'        => 'bank',
        ]);

        // BankAccountFactory uses 'checking' which is NOT in the valid enum (current/savings/credit_card/cash)
        $this->bankAccount = BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'currency_code'   => 'SAR',
            'is_active'       => true,
            'gl_account_id'   => $glAccount->id,
            'account_type'    => 'current',
        ]);
    }

    public function test_can_create_bank_reconciliation(): void
    {
        $response = $this->apiPost('/bank-reconciliation/bank-reconciliations', [
            'bank_account_id'   => $this->bankAccount->id,
            'statement_date'    => now()->format('Y-m-d'),
            'statement_balance' => 50000.00,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.bank_account_id', $this->bankAccount->id);
    }

    public function test_can_retrieve_bank_reconciliation(): void
    {
        $reconciliation = BankReconciliation::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $this->bankAccount->id,
            'status'          => 'in_progress',
            'created_by'      => $this->user->id,
            'summary'         => null,
        ]);

        $response = $this->apiGet("/bank-reconciliation/bank-reconciliations/{$reconciliation->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.id', $reconciliation->id);
    }

    public function test_can_trigger_auto_match_on_reconciliation(): void
    {
        $reconciliation = BankReconciliation::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $this->bankAccount->id,
            'status'          => 'in_progress',
            'created_by'      => $this->user->id,
            'summary'         => null,
        ]);

        $response = $this->apiPost("/bank-reconciliation/bank-reconciliations/{$reconciliation->id}/auto-match");

        // Auto-match should succeed; there may be zero matches but it should not error
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_can_complete_reconciliation(): void
    {
        $reconciliation = BankReconciliation::factory()->create([
            'organization_id'       => $this->organization->id,
            'bank_account_id'       => $this->bankAccount->id,
            'status'                => 'in_progress',
            'statement_balance'     => 10000.00,
            'book_balance'          => 10000.00,
            'adjusted_book_balance' => 10000.00,
            'difference'            => 0.00,
            'created_by'            => $this->user->id,
            'summary'               => null,
        ]);

        $response = $this->apiPost("/bank-reconciliation/bank-reconciliations/{$reconciliation->id}/complete");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_can_list_bank_reconciliations(): void
    {
        BankReconciliation::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $this->bankAccount->id,
            'created_by'      => $this->user->id,
            'summary'         => null,
        ]);

        $response = $this->apiGet('/bank-reconciliation/bank-reconciliations');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
