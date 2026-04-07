<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\PaymentReceived;
use App\Services\Sales\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Tests that PaymentService::complete() creates correctly structured journal
 * entries and that multi-tenancy is enforced on those entries.
 */
class PaymentJournalTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Account $bankAccount;
    private Account $arAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.payments.view',
            'sales.payments.create',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
        ]);

        // Chart-of-accounts entries required by JournalEntryFactory::forPaymentReceived()
        $this->arAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_RECEIVABLE,
            'code'            => '1100',
            'name'            => 'Accounts Receivable',
            'is_system'       => true,
            'currency_code'   => null,
        ]);

        $this->bankAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_BANK,
            'code'            => '1010',
            'name'            => 'Cash at Bank',
            'is_system'       => true,
            'currency_code'   => null,
        ]);

        // Wire accounts into config so the factory resolves them
        Config::set('erp.default_accounts.cash', $this->bankAccount->id);
        Config::set('erp.default_accounts.receivable', $this->arAccount->id);

        // Wire the AR account onto the customer so the factory uses the org-scoped account
        $this->customer->update(['receivable_account_id' => $this->arAccount->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | complete() — happy path
    |--------------------------------------------------------------------------
    */

    public function test_complete_creates_journal_entry_with_correct_organization_id(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => PaymentReceived::STATUS_PENDING,
            'amount'          => 1500.00,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1.0,
            'base_amount'     => 1500.00,
            'created_by'      => $this->user->id,
        ]);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);
        $completed = $service->complete($payment);

        // Payment must be COMPLETED with a journal_entry_id
        $this->assertEquals(PaymentReceived::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->journal_entry_id, 'A journal entry must be created on complete()');

        // Journal entry must belong to the same organisation
        $journalEntry = JournalEntry::findOrFail($completed->journal_entry_id);
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'Journal entry organization_id must match the payment organization_id'
        );
    }

    public function test_complete_creates_journal_entry_with_exactly_two_lines(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => PaymentReceived::STATUS_PENDING,
            'amount'          => 2000.00,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1.0,
            'base_amount'     => 2000.00,
            'created_by'      => $this->user->id,
        ]);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);
        $completed = $service->complete($payment);

        $lines = JournalEntryLine::where('journal_entry_id', $completed->journal_entry_id)->get();

        $this->assertCount(2, $lines, 'Payment journal entry must have exactly 2 lines (debit bank, credit AR)');
    }

    public function test_complete_journal_debit_line_equals_payment_amount(): void
    {
        $amount = 3750.00;

        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => PaymentReceived::STATUS_PENDING,
            'amount'          => $amount,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1.0,
            'base_amount'     => $amount,
            'created_by'      => $this->user->id,
        ]);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);
        $completed = $service->complete($payment);

        $lines = JournalEntryLine::where('journal_entry_id', $completed->journal_entry_id)->get();

        // Exactly one debit line (bank/cash account) with amount = payment->amount
        $debitLines = $lines->filter(fn($l) => (float) $l->debit > 0);
        $this->assertCount(1, $debitLines, 'There must be exactly one debit line');
        $this->assertEqualsWithDelta(
            $amount,
            (float) $debitLines->first()->debit,
            0.001,
            'Debit line amount must equal the payment amount'
        );
    }

    public function test_complete_journal_credit_line_equals_payment_amount_and_references_customer(): void
    {
        $amount = 5000.00;

        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => PaymentReceived::STATUS_PENDING,
            'amount'          => $amount,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1.0,
            'base_amount'     => $amount,
            'created_by'      => $this->user->id,
        ]);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);
        $completed = $service->complete($payment);

        $lines = JournalEntryLine::where('journal_entry_id', $completed->journal_entry_id)->get();

        // Exactly one credit line (AR account) with amount = payment->amount
        $creditLines = $lines->filter(fn($l) => (float) $l->credit > 0);
        $this->assertCount(1, $creditLines, 'There must be exactly one credit line');

        $creditLine = $creditLines->first();
        $this->assertEqualsWithDelta(
            $amount,
            (float) $creditLine->credit,
            0.001,
            'Credit line amount must equal the payment amount'
        );
        $this->assertEquals(
            $this->customer->id,
            $creditLine->contact_id,
            'AR credit line must reference the customer as contact_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | complete() — missing account config (graceful skip)
    |--------------------------------------------------------------------------
    */

    public function test_complete_with_missing_account_config_still_completes_payment(): void
    {
        // Remove account config to force InvalidArgumentException inside JournalEntryFactory
        Config::set('erp.default_accounts.cash', null);
        Config::set('erp.default_accounts.receivable', null);

        // Also remove the per-customer account so there is truly no account to use
        $this->customer->update(['receivable_account_id' => null]);

        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => PaymentReceived::STATUS_PENDING,
            'amount'          => 800.00,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1.0,
            'base_amount'     => 800.00,
            'bank_account_id' => null,  // no bank account either
            'created_by'      => $this->user->id,
        ]);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);

        // Must not throw — the service logs and continues
        $completed = $service->complete($payment);

        $this->assertEquals(
            PaymentReceived::STATUS_COMPLETED,
            $completed->status,
            'Payment must be COMPLETED even when journal entry creation is skipped'
        );
        $this->assertNull(
            $completed->journal_entry_id,
            'journal_entry_id must be null when journal creation was skipped'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | void() — null journal entry does not throw
    |--------------------------------------------------------------------------
    */

    public function test_void_with_null_journal_entry_does_not_throw(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => PaymentReceived::STATUS_COMPLETED,
            'journal_entry_id' => null,
            'amount'          => 500.00,
            'currency_code'   => 'SAR',
            'created_by'      => $this->user->id,
        ]);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);

        // Must not throw despite journal_entry_id being null
        $voided = $service->void($payment, 'Test void reason');

        $this->assertEquals(
            PaymentReceived::STATUS_VOIDED,
            $voided->status,
            'Payment must transition to VOIDED'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy isolation
    |--------------------------------------------------------------------------
    */

    public function test_journal_entry_organization_id_is_isolated_between_tenants(): void
    {
        // --- Tenant 1 (already set up) ---
        $payment1 = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => PaymentReceived::STATUS_PENDING,
            'amount'          => 1000.00,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1.0,
            'base_amount'     => 1000.00,
            'created_by'      => $this->user->id,
        ]);

        // --- Tenant 2 ---
        // Use a fresh setUpOrganization call that will re-seed currencies
        $savedOrg   = $this->organization;
        $savedBranch = $this->branch;

        $this->setUpOrganization('AE');
        $org2 = $this->organization;

        $customer2 = Contact::factory()->create([
            'organization_id' => $org2->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'AED',
        ]);

        $arAccount2 = Account::factory()->create([
            'organization_id' => $org2->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_RECEIVABLE,
            'code'            => '1100',
            'name'            => 'Accounts Receivable',
            'currency_code'   => null,
        ]);
        $bankAccount2 = Account::factory()->create([
            'organization_id' => $org2->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_BANK,
            'code'            => '1010',
            'name'            => 'Cash at Bank',
            'currency_code'   => null,
        ]);
        $customer2->update(['receivable_account_id' => $arAccount2->id]);

        $payment2 = PaymentReceived::factory()->create([
            'organization_id' => $org2->id,
            'customer_id'     => $customer2->id,
            'status'          => PaymentReceived::STATUS_PENDING,
            'amount'          => 2000.00,
            'currency_code'   => 'AED',
            'exchange_rate'   => 1.0,
            'base_amount'     => 2000.00,
        ]);

        // --- Complete only the org1 payment ---
        Config::set('erp.default_accounts.cash', $this->bankAccount->id);
        Config::set('erp.default_accounts.receivable', $this->arAccount->id);

        $this->setUpOpenFiscalPeriod(orgId: (string) $savedOrg->id);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);
        $completed = $service->complete($payment1);

        $this->assertNotNull($completed->journal_entry_id);

        $journalEntry = JournalEntry::findOrFail($completed->journal_entry_id);

        $this->assertEquals(
            $savedOrg->id,
            $journalEntry->organization_id,
            'Journal entry must belong to org1, not org2'
        );
        $this->assertNotEquals(
            $org2->id,
            $journalEntry->organization_id,
            'Journal entry must NOT belong to org2'
        );

        // payment2 must still be PENDING — we never completed it
        $this->assertEquals(
            PaymentReceived::STATUS_PENDING,
            $payment2->fresh()->status
        );
    }
}
