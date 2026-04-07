<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\BillLine;
use App\Models\Sales\Contact;
use App\Services\Purchase\BillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Tests that BillService::approve() creates correctly structured journal
 * entries (Debit expense + tax, Credit AP) and that multi-tenancy is enforced.
 *
 * Also tests that BillService::create() surfaces the correct exception message
 * when a line has an invalid quantity.
 */
class BillJournalTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $supplier;
    private Account $apAccount;
    private Account $expenseAccount;
    private Account $taxReceivableAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.bills.view',
            'purchase.bills.create',
            'purchase.bills.approve',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
            'currency_code'   => 'SAR',
        ]);

        // Accounts Payable — used by JournalEntryFactory::forBill() as the AP credit line
        $this->apAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => Account::SUBTYPE_PAYABLE,
            'code'            => '2000',
            'name'            => 'Accounts Payable',
            'is_system'       => true,
            'currency_code'   => null,
        ]);

        // Expense account used as the debit line for bill lines
        $this->expenseAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_EXPENSE,
            'sub_type'        => Account::SUBTYPE_OPERATING_EXPENSE,
            'code'            => '5000',
            'name'            => 'Operating Expenses',
            'is_system'       => true,
            'currency_code'   => null,
        ]);

        // Tax receivable — used for the input VAT debit line when tax_amount > 0
        $this->taxReceivableAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_OTHER_ASSET,
            'code'            => '1300',
            'name'            => 'Input VAT Receivable',
            'is_system'       => true,
            'currency_code'   => null,
        ]);

        // Wire accounts into config
        Config::set('erp.default_accounts.payable', $this->apAccount->id);
        Config::set('erp.default_accounts.expense', $this->expenseAccount->id);
        Config::set('erp.default_accounts.tax_receivable', $this->taxReceivableAccount->id);

        // Wire AP account onto the supplier
        $this->supplier->update(['payable_account_id' => $this->apAccount->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a persisted Bill with one or more lines and return the loaded model.
     * Lines are created directly (bypassing BillService::create) so we can control
     * exact amounts. The bill totals are set consistently with the provided lines.
     */
    private function makeBillWithLines(
        float $subtotal,
        float $taxAmount,
        int $lineCount = 1
    ): Bill {
        $total    = $subtotal + $taxAmount;
        $lineSubtotal = round($subtotal / $lineCount, 4);
        $lineTax  = round($taxAmount / $lineCount, 4);
        $lineTotal = round($lineSubtotal + $lineTax, 4);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $this->supplier->id,
            'supplier_name'   => $this->supplier->getDisplayName(),
            'status'          => Bill::STATUS_DRAFT,
            'subtotal'        => $subtotal,
            'tax_amount'      => $taxAmount,
            'total'           => $total,
            'base_total'      => $total,
            'amount_due'      => $total,
            'amount_paid'     => 0,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1.0,
            'created_by'      => $this->user->id,
        ]);

        for ($i = 0; $i < $lineCount; $i++) {
            BillLine::factory()->create([
                'bill_id'     => $bill->id,
                'account_id'  => $this->expenseAccount->id,
                'description' => "Test expense line {$i}",
                'quantity'    => 1,
                'unit_price'  => $lineSubtotal,
                'subtotal'    => $lineSubtotal,
                'tax_rate'    => $taxAmount > 0 ? 15 : 0,
                'tax_amount'  => $lineTax,
                'total'       => $lineTotal,
            ]);
        }

        return $bill->load('lines', 'supplier');
    }

    /*
    |--------------------------------------------------------------------------
    | approve() — happy path
    |--------------------------------------------------------------------------
    */

    public function test_approve_creates_journal_entry_with_correct_organization_id(): void
    {
        $bill = $this->makeBillWithLines(subtotal: 1000.00, taxAmount: 150.00);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill, $this->user->id);

        $this->assertEquals(Bill::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->journal_entry_id, 'A journal entry must be created on approve()');

        $journalEntry = JournalEntry::findOrFail($approved->journal_entry_id);
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'Journal entry organization_id must match the bill organization_id'
        );
    }

    public function test_approve_journal_ap_credit_line_equals_bill_total(): void
    {
        $subtotal  = 2000.00;
        $taxAmount = 300.00;
        $total     = $subtotal + $taxAmount;

        $bill = $this->makeBillWithLines(subtotal: $subtotal, taxAmount: $taxAmount);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill, $this->user->id);

        $lines = JournalEntryLine::where('journal_entry_id', $approved->journal_entry_id)->get();

        // AP credit line = bill->total
        $creditLines = $lines->filter(fn($l) => (float) $l->credit > 0);
        $this->assertNotEmpty($creditLines, 'There must be at least one credit line (AP)');

        $totalCredit = $creditLines->sum(fn($l) => (float) $l->credit);
        $this->assertEqualsWithDelta(
            $total,
            $totalCredit,
            0.01,
            'Total credits must equal bill->total (AP payable)'
        );
    }

    public function test_approve_journal_expense_debit_lines_sum_equals_bill_subtotal(): void
    {
        $subtotal  = 1800.00;
        $taxAmount = 0.00;

        $bill = $this->makeBillWithLines(subtotal: $subtotal, taxAmount: $taxAmount);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill, $this->user->id);

        $lines = JournalEntryLine::where('journal_entry_id', $approved->journal_entry_id)->get();

        // When there is no tax, all debit lines are expense lines
        $debitTotal = $lines->sum(fn($l) => (float) $l->debit);
        $this->assertEqualsWithDelta(
            $subtotal,
            $debitTotal,
            0.01,
            'Sum of debit lines must equal bill->subtotal when there is no tax'
        );
    }

    public function test_approve_journal_tax_receivable_debit_line_equals_bill_tax_amount(): void
    {
        $subtotal  = 2000.00;
        $taxAmount = 300.00;

        $bill = $this->makeBillWithLines(subtotal: $subtotal, taxAmount: $taxAmount);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill, $this->user->id);

        $lines = JournalEntryLine::where('journal_entry_id', $approved->journal_entry_id)->get();

        // There should be a debit line targeting the tax_receivable account
        $taxLine = $lines->first(
            fn($l) => (int) $l->account_id === (int) $this->taxReceivableAccount->id && (float) $l->debit > 0
        );

        $this->assertNotNull($taxLine, 'A debit line for input VAT receivable must exist when bill has tax');
        $this->assertEqualsWithDelta(
            $taxAmount,
            (float) $taxLine->debit,
            0.01,
            'Tax receivable debit line amount must equal bill->tax_amount'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | approve() — missing AP account (graceful skip)
    |--------------------------------------------------------------------------
    */

    public function test_approve_with_no_ap_account_returns_null_journal_entry(): void
    {
        // Remove every possible AP account reference
        $this->supplier->update(['payable_account_id' => null]);
        Config::set('erp.default_accounts.payable', null);

        $bill = $this->makeBillWithLines(subtotal: 1000.00, taxAmount: 0.00);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill, $this->user->id);

        $this->assertEquals(
            Bill::STATUS_APPROVED,
            $approved->status,
            'Bill must be APPROVED even when journal entry creation is skipped'
        );
        $this->assertNull(
            $approved->journal_entry_id,
            'journal_entry_id must be null when no AP account is configured'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | create() — exception message for invalid line
    |--------------------------------------------------------------------------
    */

    public function test_create_throws_invalid_argument_exception_for_zero_quantity(): void
    {
        // BillService::create() reads auth()->user() — authenticate for the direct service call
        $this->actingAs($this->user, 'api');

        /** @var BillService $service */
        $service = app(BillService::class);

        // Quantity = 0 must always raise an InvalidArgumentException.
        // The message originates from the first validation layer to detect it
        // (TaxCalculatorService fires before BillService's own per-line guard).
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/quantity/i');

        $service->create(
            [
                'supplier_id'  => $this->supplier->id,
                'bill_date'    => now()->format('Y-m-d'),
                'currency_code' => 'SAR',
                'branch_id'    => $this->branch->id,
                'created_by'   => $this->user->id,
            ],
            [
                [
                    'description' => 'Zero qty line',
                    'quantity'    => 0,   // invalid — must be > 0
                    'unit_price'  => 100.00,
                ],
            ]
        );
    }

    public function test_create_exception_message_does_not_say_invoice(): void
    {
        // BillService::create() reads auth()->user() — authenticate for the direct service call
        $this->actingAs($this->user, 'api');

        /** @var BillService $service */
        $service = app(BillService::class);

        $exceptionMessage = '';

        try {
            $service->create(
                [
                    'supplier_id'  => $this->supplier->id,
                    'bill_date'    => now()->format('Y-m-d'),
                    'currency_code' => 'SAR',
                    'branch_id'    => $this->branch->id,
                    'created_by'   => $this->user->id,
                ],
                [
                    [
                        'description' => 'Invalid line',
                        'quantity'    => 0,
                        'unit_price'  => 50.00,
                    ],
                ]
            );
        } catch (\InvalidArgumentException $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertNotEmpty($exceptionMessage, 'An InvalidArgumentException must be thrown');
        $this->assertStringNotContainsStringIgnoringCase(
            'Invoice line',
            $exceptionMessage,
            'Exception message must NOT reference "Invoice line" — it must say "Bill line"'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Double-entry balance
    |--------------------------------------------------------------------------
    */

    public function test_journal_entry_debits_equal_credits(): void
    {
        $bill = $this->makeBillWithLines(subtotal: 3000.00, taxAmount: 450.00, lineCount: 3);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill, $this->user->id);

        $lines = JournalEntryLine::where('journal_entry_id', $approved->journal_entry_id)->get();

        $totalDebit  = $lines->sum(fn($l) => (float) $l->debit);
        $totalCredit = $lines->sum(fn($l) => (float) $l->credit);

        $this->assertEqualsWithDelta(
            $totalDebit,
            $totalCredit,
            0.01,
            'Journal entry must balance: sum of debits must equal sum of credits'
        );
    }

    public function test_journal_entry_balances_for_bill_without_tax(): void
    {
        $bill = $this->makeBillWithLines(subtotal: 5000.00, taxAmount: 0.00, lineCount: 2);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill, $this->user->id);

        $lines = JournalEntryLine::where('journal_entry_id', $approved->journal_entry_id)->get();

        $totalDebit  = $lines->sum(fn($l) => (float) $l->debit);
        $totalCredit = $lines->sum(fn($l) => (float) $l->credit);

        $this->assertEqualsWithDelta(
            $totalDebit,
            $totalCredit,
            0.01,
            'Journal entry must balance even when there is no tax on the bill'
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
        $bill1 = $this->makeBillWithLines(subtotal: 1000.00, taxAmount: 0.00);
        $org1Id = $this->organization->id;

        // --- Tenant 2 ---
        $this->setUpOrganization('AE');
        $org2 = $this->organization;

        $supplier2 = Contact::factory()->create([
            'organization_id' => $org2->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
            'currency_code'   => 'AED',
        ]);
        $apAccount2 = Account::factory()->create([
            'organization_id' => $org2->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => Account::SUBTYPE_PAYABLE,
            'code'            => '2000',
            'name'            => 'Accounts Payable',
            'currency_code'   => null,
        ]);
        $expenseAccount2 = Account::factory()->create([
            'organization_id' => $org2->id,
            'account_type'    => Account::TYPE_EXPENSE,
            'sub_type'        => Account::SUBTYPE_OPERATING_EXPENSE,
            'code'            => '5000',
            'name'            => 'Operating Expenses',
            'currency_code'   => null,
        ]);
        $supplier2->update(['payable_account_id' => $apAccount2->id]);

        $bill2 = Bill::factory()->create([
            'organization_id' => $org2->id,
            'supplier_id'     => $supplier2->id,
            'supplier_name'   => $supplier2->getDisplayName(),
            'status'          => Bill::STATUS_DRAFT,
            'subtotal'        => 2000.00,
            'tax_amount'      => 0.00,
            'total'           => 2000.00,
            'base_total'      => 2000.00,
            'amount_due'      => 2000.00,
            'amount_paid'     => 0,
            'currency_code'   => 'AED',
            'exchange_rate'   => 1.0,
        ]);
        BillLine::factory()->create([
            'bill_id'    => $bill2->id,
            'account_id' => $expenseAccount2->id,
            'quantity'   => 1,
            'unit_price' => 2000.00,
            'subtotal'   => 2000.00,
            'tax_rate'   => 0,
            'tax_amount' => 0,
            'total'      => 2000.00,
        ]);
        $bill2 = $bill2->load('lines', 'supplier');

        // Restore org1 account config and approve only bill1
        Config::set('erp.default_accounts.payable', $this->apAccount->id);
        Config::set('erp.default_accounts.expense', $this->expenseAccount->id);
        Config::set('erp.default_accounts.tax_receivable', $this->taxReceivableAccount->id);

        $this->setUpOpenFiscalPeriod(orgId: (string) $org1Id);

        /** @var BillService $service */
        $service = app(BillService::class);
        $approved = $service->approve($bill1, $this->user->id);

        $this->assertNotNull($approved->journal_entry_id);
        $journalEntry = JournalEntry::findOrFail($approved->journal_entry_id);

        $this->assertEquals(
            $org1Id,
            $journalEntry->organization_id,
            'Journal entry must belong to org1'
        );
        $this->assertNotEquals(
            $org2->id,
            $journalEntry->organization_id,
            'Journal entry must NOT belong to org2'
        );

        // bill2 must remain unapproved
        $this->assertEquals(Bill::STATUS_DRAFT, $bill2->fresh()->status);
    }
}
