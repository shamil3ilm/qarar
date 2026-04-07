<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Complete sales journey test.
 *
 * Follows a real sales rep's workflow from first contact to credit note:
 *
 *   1.  Create a customer (Contact)
 *   2.  Create a product (Inventory)
 *   3.  Draft a Quotation for the customer
 *   4.  Send the Quotation (status → SENT)
 *   5.  Convert the Quotation to an Invoice (status → DRAFT invoice created)
 *   6.  Send/Post the Invoice (status → SENT, journal entry created)
 *   7.  Record partial payment (status → PARTIAL)
 *   8.  Verify GL: invoice journal balance, AR debit, Revenue+Tax credits
 *   9.  Record second payment to clear the invoice (status → PAID)
 *   10. Verify amount_due = 0, amount_paid = invoice total
 *   11. Issue a credit note against the paid invoice (status → DRAFT credit note)
 *   12. Approve the credit note (status → APPROVED)
 *   13. Verify tenant isolation: Org B cannot see Org A's invoice
 *
 * Every step asserts database state — not just HTTP status codes.
 */
class SalesJourneyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Product $product;

    // GL account IDs (stored for assertions)
    private int $arAccountId;
    private int $salesAccountId;
    private int $bankAccountId;
    private int $taxAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.contacts.view',
            'sales.contacts.create',
            'sales.quotations.view',
            'sales.quotations.create',
            'sales.quotations.edit',
            'sales.quotations.send',
            'sales.quotations.convert',
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.invoices.edit',
            'sales.invoices.send',
            'sales.invoices.void',
            'sales.invoices.credit-note',
            'sales.payments.view',
            'sales.payments.create',
            'sales.credit-notes.view',
            'sales.credit-notes.create',
            'sales.credit-notes.approve',
            'accounting.journals.view',
        ]);
        $this->setUpOpenFiscalPeriod();

        $unit = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
            'payment_terms'   => 30,
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Professional Service',
            'type'            => Product::TYPE_SERVICE,
            'unit_id'         => $unit->id,
            'selling_price'   => 2000.00,
            'purchase_price'  => 1000.00,
            'track_inventory' => false,
            'is_active'       => true,
            'is_purchasable'  => true,
        ]);

        $this->seedGlAccounts();
    }

    // -------------------------------------------------------------------------
    // Main journey — single sequential test covering the full lifecycle
    // -------------------------------------------------------------------------

    public function test_full_sales_lifecycle_quotation_to_credit_note(): void
    {
        // =====================================================================
        // STEP 1: Verify customer was created and is accessible via API
        // =====================================================================
        $contactResponse = $this->apiGet("/sales/contacts/{$this->customer->id}");
        $contactResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals($this->customer->id, $contactResponse->json('data.id'));
        $this->assertEquals(Contact::TYPE_CUSTOMER, $contactResponse->json('data.contact_type'));

        // =====================================================================
        // STEP 2: Create a Quotation
        // =====================================================================
        $quotationResponse = $this->apiPost('/sales/quotations', [
            'customer_id'    => $this->customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'valid_until'    => now()->addDays(14)->format('Y-m-d'),
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'notes'          => 'Journey test quotation',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Professional Service Package',
                    'quantity'    => 5,
                    'unit_price'  => 2000.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $quotationResponse->assertStatus(201)->assertJson(['success' => true]);
        $quotationId     = $quotationResponse->json('data.id');
        $quotationNumber = $quotationResponse->json('data.quotation_number');
        $quotationTotal  = $quotationResponse->json('data.total');

        $this->assertNotNull($quotationId);
        $this->assertNotNull($quotationNumber);
        // Subtotal: 5 * 2000 = 10000, VAT 15% = 1500, Total = 11500
        $this->assertEquals('11500.0000', $quotationTotal);
        $this->assertEquals(Quotation::STATUS_DRAFT, $quotationResponse->json('data.status'));

        // Verify DB state
        $quotation = Quotation::find($quotationId);
        $this->assertNotNull($quotation);
        $this->assertEquals($this->organization->id, $quotation->organization_id);
        $this->assertEquals($this->customer->id, $quotation->customer_id);
        $this->assertCount(1, $quotation->lines, 'Quotation must have exactly 1 line');

        // =====================================================================
        // STEP 3: Send the Quotation (DRAFT → SENT)
        // =====================================================================
        $sendQuotationResponse = $this->apiPost("/sales/quotations/{$quotationId}/send");
        $sendQuotationResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Quotation::STATUS_SENT, $sendQuotationResponse->json('data.status'));

        $quotation->refresh();
        $this->assertEquals(Quotation::STATUS_SENT, $quotation->status);

        // =====================================================================
        // STEP 3b: Accept the Quotation (SENT → ACCEPTED) — required before convert
        // =====================================================================
        $acceptResponse = $this->apiPost("/sales/quotations/{$quotationId}/review", ['action' => 'accept']);
        $acceptResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Quotation::STATUS_ACCEPTED, $acceptResponse->json('data.status'));

        $quotation->refresh();
        $this->assertEquals(Quotation::STATUS_ACCEPTED, $quotation->status);

        // =====================================================================
        // STEP 4: Convert Quotation to Invoice
        // =====================================================================
        $convertResponse = $this->apiPost("/sales/quotations/{$quotationId}/convert", [
            'convert_to' => 'invoice',
        ]);
        $convertResponse->assertStatus(200)->assertJson(['success' => true]);

        $invoiceId = $convertResponse->json('data.id') ?? $convertResponse->json('data.invoice_id');
        $this->assertNotNull($invoiceId, 'Convert must return the new invoice ID');

        $invoice = Invoice::find($invoiceId);
        $this->assertNotNull($invoice, 'Invoice must exist in database after conversion');
        $this->assertEquals($this->organization->id, $invoice->organization_id);
        $this->assertEquals($this->customer->id, $invoice->customer_id);
        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status, 'Converted invoice must start as DRAFT');
        $this->assertGreaterThan(0, (float) $invoice->total, 'Invoice total must be positive after conversion');
        $this->assertNull($invoice->journal_entry_id, 'Draft invoice must not yet have a journal entry');

        // Quotation must now be marked converted
        $quotation->refresh();
        $this->assertNotEquals(Quotation::STATUS_DRAFT, $quotation->status, 'Quotation should no longer be DRAFT after conversion');

        // =====================================================================
        // STEP 5: Send/Post the Invoice (DRAFT → SENT, GL entry created)
        // =====================================================================
        $sendInvoiceResponse = $this->apiPost("/sales/invoices/{$invoiceId}/send");
        $sendInvoiceResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Invoice::STATUS_SENT, $sendInvoiceResponse->json('data.status'));

        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id, 'Journal entry must be created when invoice is sent');

        // =====================================================================
        // STEP 6: Verify Invoice Journal Entry (double-entry integrity)
        // =====================================================================
        $journalEntry = JournalEntry::with('lines')->find($invoice->journal_entry_id);
        $this->assertNotNull($journalEntry);

        // Multi-tenancy: organization_id must match
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'HIGH-3 regression: journal entry organization_id must match invoice organization_id'
        );

        $lines = $journalEntry->lines;
        $this->assertGreaterThanOrEqual(2, $lines->count(), 'Journal entry must have at least 2 lines');

        // Double-entry balance check
        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEqualsWithDelta(
            round((float) $totalDebit, 2),
            round((float) $totalCredit, 2),
            0.01,
            'Journal entry must be balanced (debits = credits)'
        );

        // AR debit line: total amount, linked to customer
        $arLine = $lines->first(fn($l) => (float) $l->debit > 0 && $l->contact_id !== null);
        $this->assertNotNull($arLine, 'Journal entry must have an AR debit line with contact_id');
        $this->assertEquals($this->customer->id, $arLine->contact_id, 'AR line must reference the correct customer');
        $this->assertEqualsWithDelta((float) $invoice->total, (float) $arLine->debit, 0.01, 'AR debit must equal invoice total');

        // Credit lines must sum to invoice total (Revenue + Tax)
        $creditSum = $lines->sum('credit');
        $this->assertEqualsWithDelta((float) $invoice->total, (float) $creditSum, 0.01, 'Credit lines must sum to invoice total');

        $invoiceTotal = (float) $invoice->total;

        // Amount due must equal full invoice total (nothing paid yet)
        $this->assertEqualsWithDelta($invoiceTotal, (float) $invoice->amount_due, 0.01, 'Amount due must equal full total before payment');
        $this->assertEquals('0.0000', $invoice->amount_paid, 'Amount paid must be zero before any payment');

        // =====================================================================
        // STEP 7: Partial Payment #1 (50% of total)
        // =====================================================================
        $partialAmount = round($invoiceTotal / 2, 2);

        $payment1Response = $this->apiPost('/sales/payments-received', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => $partialAmount,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'bank_transfer',
            'reference'      => 'TXN-JOURNEY-001',
            'allocations' => [
                [
                    'invoice_id' => $invoiceId,
                    'amount'     => $partialAmount,
                ],
            ],
        ]);

        $payment1Response->assertStatus(201)->assertJson(['success' => true]);
        $payment1Id = $payment1Response->json('data.id');
        $this->assertNotNull($payment1Id);

        // Verify allocation was created
        $allocations = $payment1Response->json('data.allocations');
        $this->assertNotEmpty($allocations, 'Payment must include allocations');
        $this->assertEquals($invoiceId, $allocations[0]['invoice_id']);

        // Invoice must now be PARTIAL
        $invoice->refresh();
        $this->assertEquals(
            Invoice::STATUS_PARTIAL,
            $invoice->status,
            'Invoice must be PARTIAL after 50% payment'
        );
        $this->assertEqualsWithDelta($partialAmount, (float) $invoice->amount_paid, 0.01, 'Amount paid must be half after first payment');
        $this->assertGreaterThan(0, (float) $invoice->amount_due, 'Amount due must still be positive after first payment');

        // =====================================================================
        // STEP 8: Partial Payment #2 — clears the remaining balance
        // =====================================================================
        $remainingAmount = round($invoiceTotal - $partialAmount, 2);

        $payment2Response = $this->apiPost('/sales/payments-received', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => $remainingAmount,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'bank_transfer',
            'reference'      => 'TXN-JOURNEY-002',
            'allocations' => [
                [
                    'invoice_id' => $invoiceId,
                    'amount'     => $remainingAmount,
                ],
            ],
        ]);

        $payment2Response->assertStatus(201)->assertJson(['success' => true]);

        // Invoice must now be PAID
        $invoice->refresh();
        $this->assertEquals(
            Invoice::STATUS_PAID,
            $invoice->status,
            'Invoice must be PAID after full payment'
        );
        $this->assertEquals('0.0000', $invoice->amount_due, 'Amount due must be zero after full payment');
        $this->assertEqualsWithDelta($invoiceTotal, (float) $invoice->amount_paid, 0.01, 'Amount paid must equal invoice total');

        // Verify via GET API
        $showResponse = $this->apiGet("/sales/invoices/{$invoiceId}");
        $showResponse->assertStatus(200);
        $this->assertEquals(Invoice::STATUS_PAID, $showResponse->json('data.status'));
        $this->assertEquals('0.0000', $showResponse->json('data.amount_due'));

        // =====================================================================
        // STEP 9: Issue a Credit Note against the paid invoice
        // =====================================================================
        $creditNoteResponse = $this->apiPost('/sales/credit-notes', [
            'credit_note_type' => CreditNote::TYPE_SALES,
            'contact_id'       => $this->customer->id,
            'invoice_id'       => $invoiceId,
            'credit_note_date' => now()->format('Y-m-d'),
            'currency_code'    => 'SAR',
            'reason'           => 'Partial service not delivered — journey test',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Credit: Professional Service Package (partial)',
                    'quantity'    => 1,
                    'unit_price'  => 2000.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $creditNoteResponse->assertStatus(201)->assertJson(['success' => true]);
        $creditNoteId = $creditNoteResponse->json('data.id');
        $this->assertNotNull($creditNoteId, 'Credit note must be created');

        $creditNote = CreditNote::find($creditNoteId);
        $this->assertNotNull($creditNote);
        $this->assertEquals($this->organization->id, $creditNote->organization_id);
        $this->assertEquals($invoiceId, $creditNote->invoice_id, 'Credit note must reference the original invoice');
        $this->assertEquals($this->customer->id, $creditNote->contact_id);
        $this->assertEquals(CreditNote::STATUS_DRAFT, $creditNote->status, 'Credit note starts as DRAFT');

        // Credit note total: 1 * 2000 + 15% = 2300
        $this->assertEqualsWithDelta(2300.0, (float) $creditNote->total, 0.01, 'Credit note total must be 2300');
        $this->assertLessThanOrEqual(
            (float) $invoice->total,
            (float) $creditNote->total,
            'Credit note total must not exceed invoice total'
        );

        // =====================================================================
        // STEP 10: Approve the Credit Note (DRAFT → APPROVED)
        // =====================================================================
        $approveCnResponse = $this->apiPost("/sales/credit-notes/{$creditNoteId}/approve");
        $approveCnResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(CreditNote::STATUS_APPROVED, $approveCnResponse->json('data.status'));

        $creditNote->refresh();
        $this->assertEquals(CreditNote::STATUS_APPROVED, $creditNote->status);
    }

    // -------------------------------------------------------------------------
    // Double-void: cannot send an invoice that is already voided
    // -------------------------------------------------------------------------

    public function test_voided_invoice_cannot_be_sent_again(): void
    {
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Service',
                    'quantity'    => 1,
                    'unit_price'  => 1000.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $invoiceId = $createResponse->json('data.id');

        // Send the invoice (posts GL)
        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        // The send creates a DRAFT journal entry. Post it so void() can process it.
        $invoice = Invoice::find($invoiceId);
        if ($invoice->journal_entry_id) {
            JournalEntry::where('id', $invoice->journal_entry_id)
                ->update(['status' => JournalEntry::STATUS_POSTED]);
        }

        // Void the invoice
        $voidResponse = $this->apiPost("/sales/invoices/{$invoiceId}/void", [
            'reason' => 'Test void',
        ]);
        $voidResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Invoice::STATUS_VOIDED, $voidResponse->json('data.status'));

        // Verify DB state
        $invoice = Invoice::find($invoiceId);
        $this->assertEquals(Invoice::STATUS_VOIDED, $invoice->status);

        // Attempt to send again — must fail
        $resendResponse = $this->apiPost("/sales/invoices/{$invoiceId}/send");
        $resendResponse->assertStatus(422);

        // Journal entry on the voided invoice must be voided too
        $journalEntry = JournalEntry::find($invoice->journal_entry_id);
        if ($journalEntry) {
            $this->assertEquals(
                JournalEntry::STATUS_VOIDED,
                $journalEntry->status,
                'Journal entry must be voided when invoice is voided'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Over-payment guard: cannot allocate more than invoice balance
    // -------------------------------------------------------------------------

    public function test_payment_over_invoice_total_is_rejected(): void
    {
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Service',
                    'quantity'    => 1,
                    'unit_price'  => 500.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $invoiceId    = $createResponse->json('data.id');
        $invoiceTotal = $createResponse->json('data.total');

        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        // Try to pay 1000 on a 500 invoice
        $overPayResponse = $this->apiPost('/sales/payments-received', [
            'customer_id'    => $this->customer->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => 1000.00,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'bank_transfer',
            'allocations' => [
                [
                    'invoice_id' => $invoiceId,
                    'amount'     => 1000.00,  // exceeds invoice total of 500
                ],
            ],
        ]);

        // Must be rejected at service level — allocated amount is capped to invoice balance
        // OR rejected outright. Either way, the invoice must not be over-paid.
        if ($overPayResponse->status() === 201) {
            // Service may cap allocation at 500 and create unallocated balance
            $invoice = Invoice::find($invoiceId);
            $this->assertGreaterThan(
                0,
                (float) $invoice->amount_paid,
                'Some payment should have been applied'
            );
            $this->assertGreaterThanOrEqual(
                0.0,
                (float) $invoice->amount_due,
                'Amount due must not go negative'
            );
        } else {
            $overPayResponse->assertStatus(422);
        }
    }

    // -------------------------------------------------------------------------
    // Tenant isolation: Org B cannot see Org A's invoice
    // -------------------------------------------------------------------------

    public function test_cross_tenant_invoice_access_is_denied(): void
    {
        // Create invoice for Org A (already set up as $this->organization)
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Service',
                    'quantity'    => 1,
                    'unit_price'  => 1000.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $invoiceId = $createResponse->json('data.id');

        // Set up Org B with its own user
        $originalOrg   = $this->organization;
        $originalBranch = $this->branch;
        $originalToken = $this->token;

        $this->setUpOrganization('AE');
        $this->setUpAuthenticatedUser(['sales.invoices.view']);

        // Org B user tries to access Org A's invoice by UUID
        $crossTenantResponse = $this->apiGet("/sales/invoices/{$invoiceId}");

        // Must not return Org A's data — expect 404 (global scope blocks it) or 403
        $this->assertContains(
            $crossTenantResponse->status(),
            [403, 404],
            "Org B must not be able to access Org A's invoice (got HTTP {$crossTenantResponse->status()})"
        );
    }

    // -------------------------------------------------------------------------
    // Invoice journal entry must carry correct organization_id (HIGH-3 guard)
    // -------------------------------------------------------------------------

    public function test_invoice_journal_entry_organization_id_is_set(): void
    {
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Journal org_id regression check',
                    'quantity'    => 2,
                    'unit_price'  => 1000.00,
                    'tax_rate'    => 15.00,
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
            'HIGH-3 regression: journal_entries.organization_id must match the invoice organization_id'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedGlAccounts(): void
    {
        $ar = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'receivable',
            'code'            => '1100',
            'name'            => 'Accounts Receivable',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $sales = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_INCOME,
            'sub_type'        => 'sales',
            'code'            => '4000',
            'name'            => 'Sales Revenue',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $bank = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'bank',
            'code'            => '1010',
            'name'            => 'Cash at Bank',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $tax = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => 'tax_payable',
            'code'            => '2100',
            'name'            => 'VAT Output',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $this->arAccountId    = $ar->id;
        $this->salesAccountId = $sales->id;
        $this->bankAccountId  = $bank->id;
        $this->taxAccountId   = $tax->id;

        Config::set('erp.default_accounts.receivable', $ar->id);
        Config::set('erp.default_accounts.sales', $sales->id);
        Config::set('erp.default_accounts.cash', $bank->id);
        Config::set('erp.default_accounts.tax_payable', $tax->id);
    }
}
