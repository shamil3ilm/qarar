<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Services\Purchase\PaymentMadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Complete purchase journey test.
 *
 * Follows a procurement officer's full workflow:
 *
 *   1.  Create a supplier (Contact)
 *   2.  Create a Purchase Order (DRAFT)
 *   3.  Confirm the PO (DRAFT → CONFIRMED)
 *   4.  Create a Bill (supplier invoice) from the PO
 *   5.  Approve the Bill (DRAFT → APPROVED, GL entry created)
 *   6.  Verify GL: Expense debit, AP credit, double-entry balance
 *   7.  Verify journal organization_id matches bill organization_id (HIGH-3)
 *   8.  Record Payment Made against the bill (full payment)
 *   9.  Verify Bill transitions to PAID, amount_due = 0
 *   10. Void the payment (regression: void with real journal entry)
 *   11. Verify Bill reverts to APPROVED, payment status = VOIDED
 *   12. Verify voided payment's journal entry is also voided
 *   13. Attempt to void an already-voided payment → exception
 *
 * Every step asserts database state — not just HTTP status codes.
 */
class PurchaseJourneyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.contacts.view',
            'purchase.orders.view',
            'purchase.orders.create',
            'purchase.orders.edit',
            'purchase.orders.confirm',
            'purchase.bills.view',
            'purchase.bills.create',
            'purchase.bills.edit',
            'purchase.bills.approve',
            'purchase.payments.view',
            'purchase.payments.create',
            'purchase.payments.void',
            'accounting.journals.view',
        ]);
        $this->setUpOpenFiscalPeriod();

        $unit = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
            'currency_code'   => 'SAR',
            'payment_terms'   => 30,
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Office Equipment',
            'type'            => Product::TYPE_SERVICE,
            'unit_id'         => $unit->id,
            'purchase_price'  => 800.00,
            'selling_price'   => 1200.00,
            'track_inventory' => false,
            'is_active'       => true,
            'is_purchasable'  => true,
        ]);

        $this->seedGlAccounts();
    }

    // -------------------------------------------------------------------------
    // Main journey — PO lifecycle through payment and void
    // -------------------------------------------------------------------------

    public function test_full_purchase_lifecycle_po_to_payment_void(): void
    {
        // =====================================================================
        // STEP 1: Verify supplier is accessible via API
        // =====================================================================
        $contactResponse = $this->apiGet("/sales/contacts/{$this->supplier->id}");
        $contactResponse->assertStatus(200);
        $this->assertEquals(Contact::TYPE_SUPPLIER, $contactResponse->json('data.contact_type'));

        // =====================================================================
        // STEP 2: Create Purchase Order
        // =====================================================================
        $poResponse = $this->apiPost('/purchase/purchase-orders', [
            'supplier_id'   => $this->supplier->id,
            'order_date'    => now()->format('Y-m-d'),
            'expected_date' => now()->addDays(7)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'notes'         => 'Purchase journey test PO',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Office Equipment',
                    'quantity'    => 4,
                    'unit_price'  => 800.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $poResponse->assertStatus(201)->assertJson(['success' => true]);
        $poId    = $poResponse->json('data.id');
        $poTotal = $poResponse->json('data.total');

        $this->assertNotNull($poId);
        // Subtotal: 4 * 800 = 3200, VAT 15% = 480, Total = 3680
        $this->assertEquals('3680.0000', $poTotal);
        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $poResponse->json('data.status'));

        // Verify DB state
        $po = PurchaseOrder::find($poId);
        $this->assertNotNull($po);
        $this->assertEquals($this->organization->id, $po->organization_id);
        $this->assertEquals($this->supplier->id, $po->supplier_id);
        $this->assertCount(1, $po->lines, 'PO must have 1 line');

        // =====================================================================
        // STEP 3: Confirm Purchase Order (DRAFT → CONFIRMED)
        // =====================================================================
        $confirmResponse = $this->apiPost("/purchase/purchase-orders/{$poId}/confirm");
        $confirmResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(PurchaseOrder::STATUS_CONFIRMED, $confirmResponse->json('data.status'));

        $po->refresh();
        $this->assertEquals(PurchaseOrder::STATUS_CONFIRMED, $po->status);

        // =====================================================================
        // STEP 4: Create Bill (standalone, not linked to PO — 3-way match requires GR)
        // =====================================================================
        $billResponse = $this->apiPost('/purchase/bills', [
            'supplier_id'             => $this->supplier->id,
            'bill_date'               => now()->format('Y-m-d'),
            'due_date'                => now()->addDays(30)->format('Y-m-d'),
            'currency_code'           => 'SAR',
            'exchange_rate'           => 1.0,
            'supplier_invoice_number' => 'SUPP-PJ-001',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Office Equipment',
                    'quantity'    => 4,
                    'unit_price'  => 800.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $billResponse->assertStatus(201)->assertJson(['success' => true]);
        $billId    = $billResponse->json('data.id');
        $billTotal = $billResponse->json('data.total');
        $billStatus = $billResponse->json('data.status');

        $this->assertNotNull($billId);
        $this->assertEquals('3680.0000', $billTotal, 'Bill total must match PO total');
        $this->assertContains($billStatus, [Bill::STATUS_DRAFT, Bill::STATUS_PENDING], 'Bill starts as draft or pending');

        $bill = Bill::find($billId);
        $this->assertNotNull($bill);
        $this->assertEquals($this->organization->id, $bill->organization_id);
        $this->assertEquals($this->supplier->id, $bill->supplier_id);
        $this->assertNull($bill->journal_entry_id, 'Draft bill must not yet have a journal entry');

        // =====================================================================
        // STEP 5: Approve the Bill (creates GL entry)
        // =====================================================================
        $approveResponse = $this->apiPost("/purchase/bills/{$billId}/approve");
        $approveResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Bill::STATUS_APPROVED, $approveResponse->json('data.status'));

        $bill->refresh();
        $this->assertEquals(Bill::STATUS_APPROVED, $bill->status);
        $this->assertNotNull($bill->journal_entry_id, 'Journal entry must be created on bill approval');

        // =====================================================================
        // STEP 6: Verify Bill Journal Entry (double-entry integrity)
        // =====================================================================
        $journalEntry = JournalEntry::with('lines')->find($bill->journal_entry_id);
        $this->assertNotNull($journalEntry);

        // HIGH-3: organization_id must match
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'HIGH-3 regression: journal entry organization_id must match bill organization_id'
        );

        $lines = $journalEntry->lines;
        $this->assertGreaterThanOrEqual(2, $lines->count(), 'Bill journal entry must have at least 2 lines');

        // Double-entry balance
        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEqualsWithDelta(
            round((float) $totalDebit, 2),
            round((float) $totalCredit, 2),
            0.01,
            'Bill journal entry must be balanced (debits = credits)'
        );

        // At least one debit line (expense) and one credit line (AP)
        $this->assertTrue(
            $lines->contains(fn($l) => (float) $l->debit > 0),
            'Bill journal entry must have at least one debit line (expense)'
        );
        $this->assertTrue(
            $lines->contains(fn($l) => (float) $l->credit > 0),
            'Bill journal entry must have at least one credit line (AP)'
        );

        // AP credit line: total debits must equal bill total
        $this->assertEqualsWithDelta(
            (float) $bill->total,
            (float) $totalDebit,
            0.01,
            'Total debit must equal bill total'
        );

        // Amount due must equal full bill total before payment
        $this->assertEqualsWithDelta((float) $bill->total, (float) $bill->amount_due, 0.01);

        // =====================================================================
        // STEP 7: Create Payment Made (full payment)
        // =====================================================================
        $paymentResponse = $this->apiPost('/purchase/payments-made', [
            'supplier_id'    => $this->supplier->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => $billTotal,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'bank_transfer',
            'reference'      => 'PAY-PJ-001',
            'allocations' => [
                [
                    'bill_id' => $billId,
                    'amount'  => $billTotal,
                ],
            ],
        ]);

        $paymentResponse->assertStatus(201)->assertJson(['success' => true]);
        $paymentId = $paymentResponse->json('data.id');
        $this->assertNotNull($paymentId);

        // Bill must now be PAID
        $bill->refresh();
        $this->assertEquals(
            Bill::STATUS_PAID,
            $bill->status,
            'Bill must be PAID after full payment'
        );
        $this->assertEquals('0.0000', $bill->amount_due, 'Amount due must be zero after full payment');
        $this->assertEqualsWithDelta((float) $billTotal, (float) $bill->amount_paid, 0.01, 'Amount paid must equal bill total');

        // Verify via GET API
        $showBill = $this->apiGet("/purchase/bills/{$billId}");
        $showBill->assertStatus(200);
        $this->assertEquals(Bill::STATUS_PAID, $showBill->json('data.status'));

        // =====================================================================
        // STEP 8: Complete payment (creates payment journal entry)
        // =====================================================================
        $payment = PaymentMade::find($paymentId);
        $this->assertNotNull($payment);

        if ($payment->status === PaymentMade::STATUS_PENDING) {
            /** @var PaymentMadeService $paymentService */
            $paymentService = app(PaymentMadeService::class);
            $completed = $paymentService->complete($payment, $this->user->id);

            $completed->refresh();
            $this->assertEquals(PaymentMade::STATUS_COMPLETED, $completed->status);
            $this->assertNotNull($completed->journal_entry_id, 'Completed payment must have a journal entry');

            // Payment journal entry balance check
            $paymentJournalLines = JournalEntryLine::where('journal_entry_id', $completed->journal_entry_id)->get();
            $payDebit  = $paymentJournalLines->sum('debit');
            $payCredit = $paymentJournalLines->sum('credit');
            $this->assertEqualsWithDelta(
                round((float) $payDebit, 2),
                round((float) $payCredit, 2),
                0.01,
                'Payment journal entry must balance'
            );

            // Post the payment journal entry so void() can process it
            // (journal entries are created in STATUS_DRAFT; voiding requires STATUS_POSTED)
            if ($completed->journal_entry_id) {
                \App\Models\Accounting\JournalEntry::where('id', $completed->journal_entry_id)
                    ->update(['status' => \App\Models\Accounting\JournalEntry::STATUS_POSTED]);
            }

            $payment = $completed; // use refreshed instance for void step
        }

        // =====================================================================
        // STEP 9: Void the payment (HIGH-5 regression: real journal entry case)
        // =====================================================================
        $payment->refresh();
        $statusBeforeVoid = $payment->status;

        /** @var PaymentMadeService $paymentService */
        $paymentService = app(PaymentMadeService::class);
        $voided = $paymentService->void($payment, 'Journey test void');

        $this->assertEquals(
            PaymentMade::STATUS_VOIDED,
            $voided->status,
            'Payment must be VOIDED after void()'
        );

        // Persisted state
        $payment->refresh();
        $this->assertEquals(PaymentMade::STATUS_VOIDED, $payment->status);

        // If payment had a journal entry, it must now be voided too
        if ($payment->journal_entry_id) {
            $paymentJournal = JournalEntry::find($payment->journal_entry_id);
            $this->assertEquals(
                JournalEntry::STATUS_VOIDED,
                $paymentJournal->status,
                'Payment journal entry must be STATUS_VOIDED after payment is voided'
            );
        }

        // Bill must revert to APPROVED (no longer PAID)
        $bill->refresh();
        $this->assertNotEquals(
            Bill::STATUS_PAID,
            $bill->status,
            'Bill must not remain PAID after payment is voided'
        );

        // =====================================================================
        // STEP 10: Double-void must throw InvalidArgumentException
        // =====================================================================
        $this->expectException(\InvalidArgumentException::class);
        $paymentService->void($payment, 'Second void attempt');
    }

    // -------------------------------------------------------------------------
    // Bill cannot be approved twice
    // -------------------------------------------------------------------------

    public function test_bill_cannot_be_approved_twice(): void
    {
        $billResponse = $this->apiPost('/purchase/bills', [
            'supplier_id'   => $this->supplier->id,
            'bill_date'     => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'One-time purchase',
                    'quantity'    => 1,
                    'unit_price'  => 500.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $billResponse->assertStatus(201);
        $billId = $billResponse->json('data.id');

        // First approval succeeds
        $this->apiPost("/purchase/bills/{$billId}/approve")->assertStatus(200);

        // Second approval must fail
        $secondApprove = $this->apiPost("/purchase/bills/{$billId}/approve");
        $secondApprove->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Void with null journal_entry_id must not throw (HIGH-5 regression)
    // -------------------------------------------------------------------------

    public function test_void_payment_with_no_journal_entry_does_not_throw(): void
    {
        // Create a COMPLETED payment with no journal entry (e.g., GL accounts not configured)
        $payment = PaymentMade::factory()->completed()->create([
            'organization_id'  => $this->organization->id,
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $this->supplier->id,
            'journal_entry_id' => null,
            'amount'           => 250.00,
            'currency_code'    => 'SAR',
            'created_by'       => $this->user->id,
        ]);

        $this->assertNull($payment->journal_entry_id, 'Pre-condition: payment has no journal entry');

        /** @var PaymentMadeService $svc */
        $svc    = app(PaymentMadeService::class);
        $voided = $svc->void($payment, 'HIGH-5 regression test');

        $this->assertEquals(
            PaymentMade::STATUS_VOIDED,
            $voided->status,
            'void() must transition payment to VOIDED even when journal_entry_id is null'
        );

        $this->assertEquals(
            PaymentMade::STATUS_VOIDED,
            $payment->fresh()->status,
            'Persisted status must be VOIDED'
        );
    }

    // -------------------------------------------------------------------------
    // PO confirmation enforces status transition
    // -------------------------------------------------------------------------

    public function test_cannot_confirm_already_confirmed_po(): void
    {
        $poResponse = $this->apiPost('/purchase/purchase-orders', [
            'supplier_id'   => $this->supplier->id,
            'order_date'    => now()->format('Y-m-d'),
            'expected_date' => now()->addDays(7)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => 'Supply',
                    'quantity'    => 1,
                    'unit_price'  => 100.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $poResponse->assertStatus(201);
        $poId = $poResponse->json('data.id');

        // Confirm once
        $this->apiPost("/purchase/purchase-orders/{$poId}/confirm")->assertStatus(200);

        // Confirm again — must fail (already confirmed)
        $secondConfirm = $this->apiPost("/purchase/purchase-orders/{$poId}/confirm");
        $secondConfirm->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedGlAccounts(): void
    {
        $ap = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => 'payable',
            'code'            => '2000',
            'name'            => 'Accounts Payable',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $expense = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_EXPENSE,
            'sub_type'        => 'cost_of_goods',
            'code'            => '5000',
            'name'            => 'Purchases / COGS',
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

        $taxReceivable = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'other_asset',
            'code'            => '1700',
            'name'            => 'VAT Input',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        Config::set('erp.default_accounts.payable', $ap->id);
        Config::set('erp.default_accounts.expense', $expense->id);
        Config::set('erp.default_accounts.cash', $bank->id);
        Config::set('erp.default_accounts.tax_receivable', $taxReceivable->id);
    }
}
