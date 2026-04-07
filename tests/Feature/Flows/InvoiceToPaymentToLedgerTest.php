<?php

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use App\Services\Sales\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Golden path flow: Invoice → Send → Payment → Ledger entries.
 *
 * Covers the core AR cycle:
 *   1. Create a draft invoice for a customer
 *   2. Send the invoice (posts to ledger)
 *   3. Record full payment against the invoice
 *   4. Verify the invoice transitions to PAID
 *   5. Verify journal entries (AR debit, Income credit on send;
 *      Bank debit, AR credit on payment)
 */
class InvoiceToPaymentToLedgerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.invoices.edit',
            'sales.invoices.send',
            'sales.payments.view',
            'sales.payments.create',
            'accounting.journals.view',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
            'payment_terms'   => 30,
        ]);

        // Accounts required by InvoiceService::createJournalEntry
        // The service resolves these by type — ensure they exist for the org.
        $arAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'receivable',
            'code'            => '1100',
            'name'            => 'Accounts Receivable',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $salesAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_INCOME,
            'sub_type'        => 'sales',
            'code'            => '4000',
            'name'            => 'Sales Revenue',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $bankAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'bank',
            'code'            => '1010',
            'name'            => 'Cash at Bank',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $taxAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => 'tax_payable',
            'code'            => '2100',
            'name'            => 'VAT Output',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        // Wire accounts into config so InvoiceService::createJournalEntry() resolves them
        Config::set('erp.default_accounts.receivable', $arAccount->id);
        Config::set('erp.default_accounts.sales', $salesAccount->id);
        Config::set('erp.default_accounts.cash', $bankAccount->id);
        Config::set('erp.default_accounts.tax_payable', $taxAccount->id);
    }

    // -------------------------------------------------------------------------
    // Step 1: Create draft invoice
    // -------------------------------------------------------------------------

    public function test_full_invoice_payment_ledger_flow(): void
    {
        $organization = $this->organization;

        // ----- STEP 1: Create draft invoice -----
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'lines' => [
                [
                    'description' => 'Consulting Service',
                    'quantity'    => 10,
                    'unit_price'  => 1000.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $createResponse->assertStatus(201)->assertJson(['success' => true]);
        $invoiceId     = $createResponse->json('data.id');
        $invoiceNumber = $createResponse->json('data.invoice_number');
        $invoiceTotal  = $createResponse->json('data.total');

        $this->assertNotNull($invoiceId);
        $this->assertNotNull($invoiceNumber);
        // Subtotal: 10 * 1000 = 10000, VAT 15% = 1500, Total = 11500
        $this->assertEquals('11500.0000', $invoiceTotal);
        $this->assertEquals(Invoice::STATUS_DRAFT, $createResponse->json('data.status'));

        // ----- STEP 2: Send (post) the invoice -----
        $sendResponse = $this->apiPost("/sales/invoices/{$invoiceId}/send");

        $sendResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Invoice::STATUS_SENT, $sendResponse->json('data.status'));

        // Journal entry must exist after send
        $invoice = Invoice::find($invoiceId);
        $this->assertNotNull($invoice->journal_entry_id, 'Journal entry should be created on send');

        $journalEntry = JournalEntry::with('lines')->find($invoice->journal_entry_id);
        $this->assertNotNull($journalEntry);

        // Double-entry: debits must equal credits
        $totalDebit  = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->sum('debit');
        $totalCredit = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->sum('credit');
        $this->assertEquals(
            round((float) $totalDebit, 2),
            round((float) $totalCredit, 2),
            'Journal entry must balance (debits = credits)'
        );

        // organization_id must match invoice's organization
        $this->assertEquals($organization->id, $journalEntry->organization_id);

        // Must have exactly one AR debit line
        $arDebitLine = $journalEntry->lines->first(fn($l) => (float)$l->debit > 0 && $l->contact_id !== null);
        $this->assertNotNull($arDebitLine, 'Expected AR debit line with contact_id');
        $this->assertEquals((float)$invoice->total, (float)$arDebitLine->debit);
        $this->assertEquals($this->customer->id, $arDebitLine->contact_id);

        // Credit lines must sum to subtotal + tax
        $totalCredit = $journalEntry->lines->sum('credit');
        $this->assertEqualsWithDelta((float)$invoice->total, (float)$totalCredit, 0.01);

        // ----- STEP 3: Record full payment -----
        $paymentResponse = $this->apiPost('/sales/payments-received', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => $invoiceTotal,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'bank_transfer',
            'reference'      => 'TXN-001',
            'allocations' => [
                [
                    'invoice_id' => $invoiceId,
                    'amount'     => $invoiceTotal,
                ],
            ],
        ]);

        $paymentResponse->assertStatus(201)->assertJson(['success' => true]);
        $paymentId = $paymentResponse->json('data.id');
        $this->assertNotNull($paymentId);

        // ----- STEP 4: Invoice must now be PAID -----
        $invoice->refresh();
        $this->assertEquals(
            Invoice::STATUS_PAID,
            $invoice->status,
            'Invoice should transition to PAID after full payment'
        );
        $this->assertEquals('0.0000', $invoice->amount_due, 'Amount due should be zero');
        $this->assertEquals($invoiceTotal, $invoice->amount_paid, 'Amount paid should equal invoice total');

        // ----- STEP 5: Verify payment allocation -----
        $allocations = $paymentResponse->json('data.allocations');
        $this->assertNotEmpty($allocations, 'Payment should have at least one allocation');
        $this->assertEquals($invoiceId, $allocations[0]['invoice_id']);

        // ----- STEP 6: Verify via API (show invoice) -----
        $showResponse = $this->apiGet("/sales/invoices/{$invoiceId}");
        $showResponse->assertStatus(200);
        $this->assertEquals(Invoice::STATUS_PAID, $showResponse->json('data.status'));
    }

    public function test_payment_creates_its_own_journal_entry(): void
    {
        // ----- Create and send invoice -----
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'lines' => [
                [
                    'description' => 'Service Fee',
                    'quantity'    => 1,
                    'unit_price'  => 5000.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $invoiceId    = $createResponse->json('data.id');
        $invoiceTotal = $createResponse->json('data.total');

        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        // Record the invoice journal entry ID so we can distinguish it later
        $invoice = Invoice::find($invoiceId);
        $invoiceJournalId = $invoice->journal_entry_id;

        // ----- Record payment via API -----
        $paymentResponse = $this->apiPost('/sales/payments-received', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => $invoiceTotal,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'bank_transfer',
            'reference'      => 'TXN-002',
            'allocations' => [
                [
                    'invoice_id' => $invoiceId,
                    'amount'     => $invoiceTotal,
                ],
            ],
        ]);

        $paymentResponse->assertStatus(201);
        $paymentId = $paymentResponse->json('data.id');

        // ----- Complete the payment via PaymentService (creates its own journal entry) -----
        $paymentRecord = PaymentReceived::find($paymentId);
        $this->assertNotNull($paymentRecord);

        /** @var PaymentService $paymentService */
        $paymentService = app(PaymentService::class);
        $completed = $paymentService->complete($paymentRecord);

        // ----- Assert a second (distinct) journal entry was created for the payment -----
        $completed->refresh();
        $this->assertNotNull($completed->journal_entry_id, 'Payment must have a journal_entry_id after complete()');
        $this->assertNotEquals(
            $invoiceJournalId,
            $completed->journal_entry_id,
            'Payment journal entry must be distinct from invoice journal entry'
        );

        // ----- Assert payment journal entry organization_id -----
        $paymentJournal = JournalEntry::with('lines')->find($completed->journal_entry_id);
        $this->assertNotNull($paymentJournal);
        $this->assertEquals($this->organization->id, $paymentJournal->organization_id);

        // ----- Must have exactly 2 lines: one DEBIT (bank) and one CREDIT (receivable) -----
        $this->assertCount(2, $paymentJournal->lines, 'Payment journal entry must have exactly 2 lines');

        $debitLine  = $paymentJournal->lines->first(fn($l) => (float)$l->debit > 0);
        $creditLine = $paymentJournal->lines->first(fn($l) => (float)$l->credit > 0);

        $this->assertNotNull($debitLine, 'Payment journal entry must have a debit line (bank)');
        $this->assertNotNull($creditLine, 'Payment journal entry must have a credit line (receivable)');

        // ----- Debit and credit amounts must equal the payment amount -----
        $this->assertEqualsWithDelta((float)$invoiceTotal, (float)$debitLine->debit, 0.01);
        $this->assertEqualsWithDelta((float)$invoiceTotal, (float)$creditLine->credit, 0.01);
    }

    public function test_journal_entry_organization_id_matches_invoice(): void
    {
        // Regression test for HIGH-3: organization_id was missing from journal entries
        // created by InvoiceService::createJournalEntry().

        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'lines' => [
                [
                    'description' => 'Regression check item',
                    'quantity'    => 1,
                    'unit_price'  => 2000.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $invoiceId = $createResponse->json('data.id');

        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        $invoice      = Invoice::find($invoiceId);
        $journalEntry = JournalEntry::find($invoice->journal_entry_id);

        $this->assertNotNull($journalEntry, 'Journal entry must exist after invoice is sent');
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'HIGH-3 regression: journal entry organization_id must match the invoice organization_id'
        );
    }

    public function test_partial_payment_sets_invoice_to_partial_status(): void
    {
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'lines' => [
                [
                    'description' => 'Annual License',
                    'quantity'    => 1,
                    'unit_price'  => 5000.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $invoiceId    = $createResponse->json('data.id');
        $invoiceTotal = (float) $createResponse->json('data.total');

        // Send the invoice
        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        // Pay half
        $partialAmount = $invoiceTotal / 2;

        $paymentResponse = $this->apiPost('/sales/payments-received', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => $partialAmount,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'cash',
            'allocations' => [
                [
                    'invoice_id' => $invoiceId,
                    'amount'     => $partialAmount,
                ],
            ],
        ]);

        $paymentResponse->assertStatus(201);

        $invoice = Invoice::find($invoiceId);
        $this->assertEquals(
            Invoice::STATUS_PARTIAL,
            $invoice->status,
            'Invoice should be PARTIAL after half payment'
        );
        $this->assertEqualsWithDelta($partialAmount, (float) $invoice->amount_paid, 0.01);
        $this->assertEqualsWithDelta($partialAmount, (float) $invoice->amount_due, 0.01);
    }

    public function test_invoice_cannot_be_sent_twice(): void
    {
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Service',
                    'quantity'    => 1,
                    'unit_price'  => 500.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $invoiceId = $createResponse->json('data.id');

        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        // Second send should fail
        $secondSend = $this->apiPost("/sales/invoices/{$invoiceId}/send");
        $secondSend->assertStatus(422);
    }

    public function test_journal_entry_lines_reference_correct_accounts(): void
    {
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Product X',
                    'quantity'    => 2,
                    'unit_price'  => 1000.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $invoiceId = $createResponse->json('data.id');
        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        $invoice = Invoice::find($invoiceId);
        $lines   = JournalEntryLine::where('journal_entry_id', $invoice->journal_entry_id)->get();

        // Must have at least AR debit and Revenue credit lines
        $this->assertGreaterThanOrEqual(2, $lines->count(), 'Journal entry must have at least 2 lines');

        // At least one debit line (AR)
        $this->assertTrue(
            $lines->contains(fn($l) => (float) $l->debit > 0),
            'Journal entry must have at least one debit line'
        );

        // At least one credit line (Revenue or VAT)
        $this->assertTrue(
            $lines->contains(fn($l) => (float) $l->credit > 0),
            'Journal entry must have at least one credit line'
        );
    }
}
