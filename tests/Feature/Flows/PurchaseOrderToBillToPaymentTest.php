<?php

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\Product;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Golden path flow: Purchase Order → Bill (Approve) → Payment Made.
 *
 * Covers the core AP cycle:
 *   1. Create a Purchase Order for a supplier
 *   2. Confirm the PO (transitions from draft → confirmed)
 *   3. Create a Bill (supplier invoice) directly (without GR, simpler path)
 *   4. Approve the Bill (posts journal entry: Expense debit, AP credit)
 *   5. Create a Payment Made against the bill
 *   6. Verify Bill transitions to PAID
 *   7. Verify journal entries balance (double-entry)
 */
class PurchaseOrderToBillToPaymentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.orders.view',
            'purchase.orders.create',
            'purchase.orders.edit',
            'purchase.orders.send',
            'purchase.orders.confirm',
            'purchase.orders.approve',
            'purchase.bills.view',
            'purchase.bills.create',
            'purchase.bills.edit',
            'purchase.bills.approve',
            'purchase.payments.view',
            'purchase.payments.create',
            'purchase.payments.complete',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
            'currency_code'   => 'SAR',
            'payment_terms'   => 30,
        ]);

        $this->product = Product::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Office Supplies',
            'purchase_price'  => 500.00,
            'selling_price'   => 700.00,
            'track_inventory' => false,
        ]);

        // GL accounts required by BillService::createJournalEntry
        $apAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => 'payable',
            'code'            => '2000',
            'name'            => 'Accounts Payable',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $expenseAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_EXPENSE,
            'sub_type'        => 'cost_of_goods',
            'code'            => '5000',
            'name'            => 'Purchases / COGS',
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

        $taxReceivable = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'other_asset',
            'code'            => '1700',
            'name'            => 'VAT Input',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        // Wire accounts into config so BillService::createJournalEntry() resolves them
        Config::set('erp.default_accounts.payable', $apAccount->id);
        Config::set('erp.default_accounts.expense', $expenseAccount->id);
        Config::set('erp.default_accounts.cash', $bankAccount->id);
        Config::set('erp.default_accounts.tax_receivable', $taxReceivable->id);
    }

    // -------------------------------------------------------------------------
    // Full AP cycle
    // -------------------------------------------------------------------------

    public function test_full_purchase_order_bill_payment_flow(): void
    {
        // ----- STEP 1: Create Purchase Order -----
        $poResponse = $this->apiPost('/purchase/purchase-orders', [
            'supplier_id'   => $this->supplier->id,
            'order_date'    => now()->format('Y-m-d'),
            'expected_date' => now()->addDays(7)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'notes'         => 'Test PO',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => $this->product->name,
                    'quantity'    => 10,
                    'unit_price'  => 500.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $poResponse->assertStatus(201)->assertJson(['success' => true]);
        $poId     = $poResponse->json('data.id');
        $poTotal  = $poResponse->json('data.total');
        $poStatus = $poResponse->json('data.status');

        $this->assertNotNull($poId);
        // Subtotal: 10 * 500 = 5000, VAT 15% = 750, Total = 5750
        $this->assertEquals('5750.0000', $poTotal);
        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $poStatus);

        // ----- STEP 2: Confirm the PO -----
        $confirmResponse = $this->apiPost("/purchase/purchase-orders/{$poId}/confirm");
        $confirmResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(PurchaseOrder::STATUS_CONFIRMED, $confirmResponse->json('data.status'));

        // ----- STEP 3: Create a Bill directly (supplier invoice received) -----
        // Note: bill is created WITHOUT purchase_order_id to represent a standalone
        // vendor invoice not requiring 3-way PO/GR/Bill match enforcement.
        // The PO created in Step 1 proves the PO lifecycle; the Bill proves the AP cycle.
        $billResponse = $this->apiPost('/purchase/bills', [
            'supplier_id'             => $this->supplier->id,
            'bill_date'               => now()->format('Y-m-d'),
            'due_date'                => now()->addDays(30)->format('Y-m-d'),
            'currency_code'           => 'SAR',
            'exchange_rate'           => 1.0,
            'supplier_invoice_number' => 'SUPP-INV-001',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => $this->product->name,
                    'quantity'    => 10,
                    'unit_price'  => 500.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $billResponse->assertStatus(201)->assertJson(['success' => true]);
        $billId     = $billResponse->json('data.id');
        $billTotal  = $billResponse->json('data.total');
        $billStatus = $billResponse->json('data.status');

        $this->assertNotNull($billId);
        $this->assertEquals('5750.0000', $billTotal);
        $this->assertContains($billStatus, [Bill::STATUS_DRAFT, Bill::STATUS_PENDING]);

        // ----- STEP 4: Approve the Bill (creates journal entry) -----
        $approveResponse = $this->apiPost("/purchase/bills/{$billId}/approve");
        $approveResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Bill::STATUS_APPROVED, $approveResponse->json('data.status'));

        // Journal entry must exist after approval
        $bill = Bill::find($billId);
        $this->assertNotNull($bill->journal_entry_id, 'Journal entry should be created on bill approval');

        // Double-entry check
        $journalLines = JournalEntryLine::where('journal_entry_id', $bill->journal_entry_id)->get();
        $totalDebit   = $journalLines->sum('debit');
        $totalCredit  = $journalLines->sum('credit');
        $this->assertEquals(
            round((float) $totalDebit, 2),
            round((float) $totalCredit, 2),
            'Bill journal entry must balance (debits = credits)'
        );

        // ----- STEP 5: Create Payment Made -----
        $paymentResponse = $this->apiPost('/purchase/payments-made', [
            'supplier_id'    => $this->supplier->id,
            'payment_date'   => now()->format('Y-m-d'),
            'amount'         => $billTotal,
            'currency_code'  => 'SAR',
            'exchange_rate'  => 1.0,
            'payment_method' => 'bank_transfer',
            'reference'      => 'PAY-SUPP-001',
            'allocations'    => [
                [
                    'bill_id' => $billId,
                    'amount'  => $billTotal,
                ],
            ],
        ]);

        $paymentResponse->assertStatus(201)->assertJson(['success' => true]);
        $paymentId = $paymentResponse->json('data.id');
        $this->assertNotNull($paymentId);

        // ----- STEP 6: Bill must now be PAID -----
        $bill->refresh();
        $this->assertEquals(
            Bill::STATUS_PAID,
            $bill->status,
            'Bill should transition to PAID after full payment'
        );
        $this->assertEquals('0.0000', $bill->amount_due, 'Amount due should be zero after full payment');

        // ----- STEP 7: Verify via API -----
        $showBill = $this->apiGet("/purchase/bills/{$billId}");
        $showBill->assertStatus(200);
        $this->assertEquals(Bill::STATUS_PAID, $showBill->json('data.status'));
    }

    public function test_bill_cannot_be_approved_without_positive_total(): void
    {
        // Edge case: empty lines would result in zero total — service should reject
        $billResponse = $this->apiPost('/purchase/bills', [
            'supplier_id'   => $this->supplier->id,
            'bill_date'     => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Zero cost item',
                    'quantity'    => 1,
                    'unit_price'  => 0.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        if ($billResponse->status() === 201) {
            $billId = $billResponse->json('data.id');
            $approveResponse = $this->apiPost("/purchase/bills/{$billId}/approve");
            // Either validation rejects at creation or approval rejects zero total
            $approveResponse->assertStatus(422);
        } else {
            // Creation itself was rejected — also acceptable
            $billResponse->assertStatus(422);
        }
    }

    public function test_purchase_order_totals_match_bill_totals(): void
    {
        $poResponse = $this->apiPost('/purchase/purchase-orders', [
            'supplier_id'   => $this->supplier->id,
            'order_date'    => now()->format('Y-m-d'),
            'expected_date' => now()->addDays(14)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => $this->product->name,
                    'quantity'    => 5,
                    'unit_price'  => 200.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $poResponse->assertStatus(201);
        $poTotal = $poResponse->json('data.total');

        // Bill for the same quantities/prices
        $billResponse = $this->apiPost('/purchase/bills', [
            'supplier_id'   => $this->supplier->id,
            'bill_date'     => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id'  => $this->product->id,
                    'description' => $this->product->name,
                    'quantity'    => 5,
                    'unit_price'  => 200.00,
                    'tax_rate'    => 15.00,
                ],
            ],
        ]);

        $billResponse->assertStatus(201);
        $billTotal = $billResponse->json('data.total');

        $this->assertEquals(
            $poTotal,
            $billTotal,
            'Bill total should match PO total for identical lines'
        );
    }
}
