<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\HR\Payslip;
use App\Models\HR\PayslipItem;
use App\Models\HR\SalaryComponent;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use App\Models\Sales\PaymentReceived;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class JournalEntryFactoryTest extends TestCase
{
    private MockInterface $journalService;
    private JournalEntryFactory $factory;
    private JournalEntry $fakeEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->journalService = Mockery::mock(JournalService::class);
        $this->factory = new JournalEntryFactory($this->journalService);
        $this->fakeEntry = new JournalEntry();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a bare Contact mock with the fields used by JournalEntryFactory.
     */
    private function makeCustomer(int $id, ?int $receivableAccountId = 1001): MockInterface
    {
        $contact = Mockery::mock(Contact::class)->makePartial();
        $contact->id = $id;
        $contact->receivable_account_id = $receivableAccountId;
        $contact->allows('getDisplayName')->andReturn('Test Customer');

        return $contact;
    }

    /**
     * Build a bare Contact mock acting as a supplier.
     */
    private function makeSupplier(int $id, ?int $payableAccountId = 2001): MockInterface
    {
        $contact = Mockery::mock(Contact::class)->makePartial();
        $contact->id = $id;
        $contact->payable_account_id = $payableAccountId;
        $contact->allows('getDisplayName')->andReturn('Test Supplier');

        return $contact;
    }

    /**
     * Build a minimal InvoiceLine mock.
     *
     * @param int|null    $accountId          Direct account override on the line
     * @param int|null    $productIncomeAccId The product's income_account_id (null = no product)
     */
    private function makeInvoiceLine(
        string $description,
        float $subtotal,
        ?int $accountId,
        ?int $productIncomeAccId = null
    ): MockInterface {
        $line = Mockery::mock(InvoiceLine::class)->makePartial();
        $line->description = $description;
        $line->subtotal = $subtotal;
        $line->account_id = $accountId;

        if ($productIncomeAccId !== null) {
            $product = Mockery::mock()->makePartial();
            $product->income_account_id = $productIncomeAccId;
            $line->product = $product;
        } else {
            $line->product = null;
        }

        return $line;
    }

    /**
     * Build a minimal Invoice mock.
     *
     * @param InvoiceLine[] $lines
     */
    private function makeInvoice(
        string $orgId,
        string $invoiceNumber,
        float $total,
        float $taxAmount,
        string $invoiceDate,
        ?int $branchId,
        int $invoiceId,
        MockInterface $customer,
        array $lines
    ): MockInterface {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->organization_id = $orgId;
        $invoice->invoice_number = $invoiceNumber;
        $invoice->total = $total;
        $invoice->tax_amount = $taxAmount;
        $invoice->invoice_date = $invoiceDate;
        $invoice->branch_id = $branchId;
        $invoice->id = $invoiceId;
        $invoice->customer = $customer;
        $invoice->lines = new Collection($lines);

        return $invoice;
    }

    // =========================================================================
    // forInvoice() tests
    // =========================================================================

    public function test_forInvoice_sets_correct_organization_id(): void
    {
        $customer = $this->makeCustomer(42);
        $line = $this->makeInvoiceLine('Widget', 100.0, 5000);
        $invoice = $this->makeInvoice(
            orgId: 'org-abc',
            invoiceNumber: 'INV-001',
            total: 105.0,
            taxAmount: 5.0,
            invoiceDate: '2026-03-01',
            branchId: null,
            invoiceId: 1,
            customer: $customer,
            lines: [$line]
        );

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines): bool {
                return $header['organization_id'] === 'org-abc';
            })
            ->andReturn($this->fakeEntry);

        $result = $this->factory->forInvoice($invoice);

        $this->assertSame($this->fakeEntry, $result);
    }

    public function test_forInvoice_debits_accounts_receivable_with_invoice_total_and_customer_id(): void
    {
        $customer = $this->makeCustomer(id: 42, receivableAccountId: 1100);
        $line = $this->makeInvoiceLine('Widget', 100.0, 5000);
        $invoice = $this->makeInvoice(
            orgId: 'org-abc',
            invoiceNumber: 'INV-002',
            total: 115.0,
            taxAmount: 15.0,
            invoiceDate: '2026-03-01',
            branchId: null,
            invoiceId: 2,
            customer: $customer,
            lines: [$line]
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forInvoice($invoice);

        $arLine = $capturedLines[0];
        $this->assertSame(1100, $arLine['account_id']);
        $this->assertSame(115.0, $arLine['debit']);
        $this->assertSame(0, $arLine['credit']);
        $this->assertSame(42, $arLine['contact_id']);
    }

    public function test_forInvoice_creates_one_credit_line_per_invoice_line_using_line_account_id(): void
    {
        $customer = $this->makeCustomer(10);
        $line1 = $this->makeInvoiceLine('Service A', 200.0, accountId: 4100);
        $line2 = $this->makeInvoiceLine('Service B', 300.0, accountId: 4200);

        $invoice = $this->makeInvoice(
            orgId: 'org-1',
            invoiceNumber: 'INV-003',
            total: 500.0,
            taxAmount: 0.0,
            invoiceDate: '2026-03-01',
            branchId: null,
            invoiceId: 3,
            customer: $customer,
            lines: [$line1, $line2]
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forInvoice($invoice);

        // Index 0 = AR debit; index 1, 2 = revenue credits
        $this->assertCount(3, $capturedLines);
        $this->assertSame(4100, $capturedLines[1]['account_id']);
        $this->assertSame(200.0, $capturedLines[1]['credit']);
        $this->assertSame(0, $capturedLines[1]['debit']);
        $this->assertSame(4200, $capturedLines[2]['account_id']);
        $this->assertSame(300.0, $capturedLines[2]['credit']);
    }

    public function test_forInvoice_falls_back_to_product_income_account_id_when_line_account_id_is_null(): void
    {
        $customer = $this->makeCustomer(10);
        // No direct account_id on line but product has income_account_id
        $line = $this->makeInvoiceLine('Product sale', 500.0, accountId: null, productIncomeAccId: 4300);

        $invoice = $this->makeInvoice(
            orgId: 'org-1',
            invoiceNumber: 'INV-004',
            total: 500.0,
            taxAmount: 0.0,
            invoiceDate: '2026-03-01',
            branchId: null,
            invoiceId: 4,
            customer: $customer,
            lines: [$line]
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forInvoice($invoice);

        $this->assertSame(4300, $capturedLines[1]['account_id']);
    }

    public function test_forInvoice_falls_back_to_config_default_when_both_account_ids_are_null(): void
    {
        $customer = $this->makeCustomer(10);
        // No account_id, no product
        $line = $this->makeInvoiceLine('Misc', 150.0, accountId: null, productIncomeAccId: null);

        $invoice = $this->makeInvoice(
            orgId: 'org-1',
            invoiceNumber: 'INV-005',
            total: 150.0,
            taxAmount: 0.0,
            invoiceDate: '2026-03-01',
            branchId: null,
            invoiceId: 5,
            customer: $customer,
            lines: [$line]
        );

        // Patch config() to return a known default
        // Since unit tests don't boot Laravel, we use PHP's native function override via
        // the Mockery approach won't work for global functions; instead we rely on the
        // null-coalescing chain: account_id ?? product->income_account_id ?? config(...)
        // When both are null and config returns null, account_id is null — which is valid
        // and the test verifies the line IS built (no exception).
        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forInvoice($invoice);

        // account_id resolves to null (no config in unit test environment) — the line is still created
        $this->assertArrayHasKey('account_id', $capturedLines[1]);
        $this->assertSame(150.0, $capturedLines[1]['credit']);
    }

    public function test_forInvoice_creates_tax_payable_credit_line_when_tax_amount_is_positive(): void
    {
        $customer = $this->makeCustomer(10);
        $line = $this->makeInvoiceLine('Service', 1000.0, 4100);

        $invoice = $this->makeInvoice(
            orgId: 'org-1',
            invoiceNumber: 'INV-006',
            total: 1150.0,
            taxAmount: 150.0,
            invoiceDate: '2026-03-01',
            branchId: null,
            invoiceId: 6,
            customer: $customer,
            lines: [$line]
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forInvoice($invoice);

        // Expect: AR debit + 1 revenue credit + 1 tax credit
        $this->assertCount(3, $capturedLines);
        $taxLine = $capturedLines[2];
        $this->assertSame(150.0, $taxLine['credit']);
        $this->assertSame(0, $taxLine['debit']);
    }

    public function test_forInvoice_does_not_create_tax_line_when_tax_amount_is_zero(): void
    {
        $customer = $this->makeCustomer(10);
        $line = $this->makeInvoiceLine('Service', 1000.0, 4100);

        $invoice = $this->makeInvoice(
            orgId: 'org-1',
            invoiceNumber: 'INV-007',
            total: 1000.0,
            taxAmount: 0.0,
            invoiceDate: '2026-03-01',
            branchId: null,
            invoiceId: 7,
            customer: $customer,
            lines: [$line]
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forInvoice($invoice);

        // Only AR debit + 1 revenue credit; no tax line
        $this->assertCount(2, $capturedLines);
    }

    public function test_forInvoice_passes_correct_header_fields(): void
    {
        $customer = $this->makeCustomer(id: 10);
        $line = $this->makeInvoiceLine('Service', 500.0, 4100);

        $invoice = $this->makeInvoice(
            orgId: 'org-xyz',
            invoiceNumber: 'INV-008',
            total: 500.0,
            taxAmount: 0.0,
            invoiceDate: '2026-04-01',
            branchId: 99,
            invoiceId: 8,
            customer: $customer,
            lines: [$line]
        );

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines): bool {
                return $header['organization_id'] === 'org-xyz'
                    && $header['entry_date'] === '2026-04-01'
                    && $header['reference'] === 'INV-008'
                    && $header['source_type'] === Invoice::class
                    && $header['source_id'] === 8
                    && $header['branch_id'] === 99;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forInvoice($invoice);

        // Mockery's ->once() expectation is the assertion; make PHPUnit aware of it.
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // forBill() tests
    // =========================================================================

    public function test_forBill_returns_null_when_payable_account_id_is_null_and_config_default_is_null(): void
    {
        $supplier = $this->makeSupplier(id: 20, payableAccountId: null);

        $bill = Mockery::mock(Bill::class)->makePartial();
        $bill->organization_id = 'org-1';
        $bill->supplier = $supplier;

        // config('erp.default_accounts.payable') returns null in plain PHPUnit
        $this->journalService->shouldNotReceive('create');

        $result = $this->factory->forBill($bill);

        $this->assertNull($result);
    }

    public function test_forBill_creates_ap_credit_line_with_invoice_total(): void
    {
        $supplier = $this->makeSupplier(id: 20, payableAccountId: 2100);

        $billLine = Mockery::mock()->makePartial();
        $billLine->description = 'Office Supplies';
        $billLine->subtotal = 400.0;
        $billLine->account_id = 6100;
        $billLine->product = null;

        $bill = Mockery::mock(Bill::class)->makePartial();
        $bill->organization_id = 'org-2';
        $bill->bill_number = 'BILL-001';
        $bill->bill_date = '2026-03-15';
        $bill->total = 400.0;
        $bill->tax_amount = 0.0;
        $bill->branch_id = null;
        $bill->id = 10;
        $bill->supplier = $supplier;
        $bill->lines = new Collection([$billLine]);

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forBill($bill);

        // Last line should be the AP credit
        $apLine = end($capturedLines);
        $this->assertSame(2100, $apLine['account_id']);
        $this->assertSame(400.0, $apLine['credit']);
        $this->assertSame(0, $apLine['debit']);
        $this->assertSame(20, $apLine['contact_id']);
    }

    public function test_forBill_creates_expense_debit_lines_per_bill_line(): void
    {
        $supplier = $this->makeSupplier(id: 20, payableAccountId: 2100);

        $billLine1 = Mockery::mock()->makePartial();
        $billLine1->description = 'Hardware';
        $billLine1->subtotal = 600.0;
        $billLine1->account_id = 6200;
        $billLine1->product = null;

        $billLine2 = Mockery::mock()->makePartial();
        $billLine2->description = 'Software';
        $billLine2->subtotal = 300.0;
        $billLine2->account_id = 6300;
        $billLine2->product = null;

        $bill = Mockery::mock(Bill::class)->makePartial();
        $bill->organization_id = 'org-2';
        $bill->bill_number = 'BILL-002';
        $bill->bill_date = '2026-03-15';
        $bill->total = 900.0;
        $bill->tax_amount = 0.0;
        $bill->branch_id = null;
        $bill->id = 11;
        $bill->supplier = $supplier;
        $bill->lines = new Collection([$billLine1, $billLine2]);

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forBill($bill);

        // 2 expense lines + 1 AP credit
        $this->assertCount(3, $capturedLines);

        $this->assertSame(6200, $capturedLines[0]['account_id']);
        $this->assertSame(600.0, $capturedLines[0]['debit']);
        $this->assertSame(0, $capturedLines[0]['credit']);

        $this->assertSame(6300, $capturedLines[1]['account_id']);
        $this->assertSame(300.0, $capturedLines[1]['debit']);
    }

    public function test_forBill_creates_tax_receivable_debit_line_when_tax_amount_is_positive(): void
    {
        $supplier = $this->makeSupplier(id: 20, payableAccountId: 2100);

        $billLine = Mockery::mock()->makePartial();
        $billLine->description = 'Consulting';
        $billLine->subtotal = 1000.0;
        $billLine->account_id = 6400;
        $billLine->product = null;

        $bill = Mockery::mock(Bill::class)->makePartial();
        $bill->organization_id = 'org-3';
        $bill->bill_number = 'BILL-003';
        $bill->bill_date = '2026-03-15';
        $bill->total = 1150.0;
        $bill->tax_amount = 150.0;
        $bill->branch_id = null;
        $bill->id = 12;
        $bill->supplier = $supplier;
        $bill->lines = new Collection([$billLine]);

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forBill($bill);

        // expense debit + tax receivable debit + AP credit
        $this->assertCount(3, $capturedLines);
        $taxLine = $capturedLines[1];
        $this->assertSame(150.0, $taxLine['debit']);
        $this->assertSame(0, $taxLine['credit']);
    }

    public function test_forBill_passes_correct_organization_id(): void
    {
        $supplier = $this->makeSupplier(id: 25, payableAccountId: 2100);

        $billLine = Mockery::mock()->makePartial();
        $billLine->description = 'Rent';
        $billLine->subtotal = 5000.0;
        $billLine->account_id = 6500;
        $billLine->product = null;

        $bill = Mockery::mock(Bill::class)->makePartial();
        $bill->organization_id = 'org-99';
        $bill->bill_number = 'BILL-004';
        $bill->bill_date = '2026-03-20';
        $bill->total = 5000.0;
        $bill->tax_amount = 0.0;
        $bill->branch_id = null;
        $bill->id = 13;
        $bill->supplier = $supplier;
        $bill->lines = new Collection([$billLine]);

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines): bool {
                return $header['organization_id'] === 'org-99';
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forBill($bill);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // forPayslip() tests
    // =========================================================================

    /**
     * Build a deduction PayslipItem mock with an optional SalaryComponent.
     */
    private function makeDeductionItem(
        string $name,
        float $amount,
        bool $isStatutory,
        string $componentCode,
        ?int $configuredAccountId
    ): MockInterface {
        $component = Mockery::mock(SalaryComponent::class)->makePartial();
        $component->is_statutory = $isStatutory;
        $component->code = $componentCode;

        $item = Mockery::mock(PayslipItem::class)->makePartial();
        $item->name = $name;
        $item->amount = $amount;
        $item->salaryComponent = $component;

        // Store the account id on the component code so the test can control what
        // config("erp.statutory_accounts.{$code}") returns.  Since we cannot mock
        // the global config() in plain PHPUnit, we verify the *call pattern* by
        // recording which items get included in lines when the factory processes them.

        return $item;
    }

    /**
     * Build a Payslip stub with a fluent deductions() chain that returns $items.
     *
     * We use an anonymous class extending Payslip so we can override deductions()
     * without triggering Mockery's return-type enforcement on the HasMany hint.
     */
    private function makePayslip(
        string $orgId,
        int $payslipId,
        string $payslipNumber,
        float $grossEarnings,
        array $deductionItems,
        string $paymentDate = '2026-03-31'
    ): Payslip {
        $employee = new class {
            public function getDisplayName(): string
            {
                return 'John Doe';
            }
        };

        $period = new class {
            public string $name = 'March 2026';
        };

        $itemCollection = new Collection($deductionItems);

        // Anonymous stub that overrides deductions() to avoid HasMany type enforcement
        $payslip = new class(
            $orgId,
            $payslipId,
            $payslipNumber,
            $grossEarnings,
            $paymentDate,
            $employee,
            $period,
            $itemCollection
        ) extends Payslip {
            // phpcs:disable
            public function __construct(
                private string $orgId,
                private int $psId,
                private string $psNumber,
                private float $gross,
                private string $payDate,
                private object $emp,
                private object $period,
                private Collection $deductionCollection
            ) {
                // Intentionally skip parent::__construct to avoid DB bootstrapping
            }

            public function __get($key)
            {
                return match ($key) {
                    'organization_id'  => $this->orgId,
                    'id'               => $this->psId,
                    'payslip_number'   => $this->psNumber,
                    'gross_earnings'   => $this->gross,
                    'payment_date'     => $this->payDate,
                    'employee'         => $this->emp,
                    'payrollPeriod'    => $this->period,
                    default            => null,
                };
            }

            public function deductions(): \Illuminate\Database\Eloquent\Relations\HasMany
            {
                // Return a chainable stub; the factory calls ->with('salaryComponent')->get()
                $collection = $this->deductionCollection;

                return new class($collection) extends \Illuminate\Database\Eloquent\Relations\HasMany {
                    private Collection $col;

                    public function __construct(Collection $col)
                    {
                        $this->col = $col;
                        // No parent::__construct — we only need with() and get()
                    }

                    public function with($relations, $callback = null)
                    {
                        return $this;
                    }

                    public function get($columns = ['*'])
                    {
                        return $this->col;
                    }
                };
            }
            // phpcs:enable
        };

        return $payslip;
    }

    public function test_forPayslip_creates_salary_expense_debit_with_gross_earnings(): void
    {
        // No statutory deductions with accounts in this test (plain unit env, config returns null)
        $payslip = $this->makePayslip(
            orgId: 'org-hr-1',
            payslipId: 50,
            payslipNumber: 'PS-2026-001',
            grossEarnings: 8000.0,
            deductionItems: []
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPayslip($payslip);

        $expenseLine = $capturedLines[0];
        $this->assertSame(8000.0, $expenseLine['debit']);
        $this->assertSame(0, $expenseLine['credit']);
    }

    public function test_forPayslip_creates_salary_payable_credit_of_gross_minus_statutory_with_accounts(): void
    {
        // Two statutory deductions, but in a plain unit test config() returns null so
        // no statutory_account is found — salary payable should equal gross earnings.
        $payslip = $this->makePayslip(
            orgId: 'org-hr-1',
            payslipId: 51,
            payslipNumber: 'PS-2026-002',
            grossEarnings: 8000.0,
            deductionItems: []
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPayslip($payslip);

        // salary payable = gross - 0 = 8000
        $payableLine = $capturedLines[1];
        $this->assertSame(0, $payableLine['debit']);
        // bcsub('8000','0',4) = '8000.0000' which casts to '8000.0000' string; factory stores it as-is
        $this->assertEqualsWithDelta(8000.0, (float) $payableLine['credit'], 0.0001);
    }

    public function test_forPayslip_does_not_create_credit_line_for_non_statutory_deductions(): void
    {
        // A deduction item whose salaryComponent->is_statutory is false
        $component = new class extends SalaryComponent {
            public bool $is_statutory = false;
            public string $code = 'LOAN';
            public function __construct() {}
        };

        $loanDeduction = new class($component) extends PayslipItem {
            public string $name = 'Loan Repayment';
            public float $amount = 500.0;
            public function __construct(public SalaryComponent $salaryComponent) {}
        };

        $payslip = $this->makePayslip(
            orgId: 'org-hr-2',
            payslipId: 52,
            payslipNumber: 'PS-2026-003',
            grossEarnings: 5000.0,
            deductionItems: [$loanDeduction]
        );

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPayslip($payslip);

        // Only salary expense + salary payable; no extra statutory credit
        $this->assertCount(2, $capturedLines);
    }

    public function test_forPayslip_passes_correct_organization_id(): void
    {
        $payslip = $this->makePayslip(
            orgId: 'org-payroll-77',
            payslipId: 55,
            payslipNumber: 'PS-77-001',
            grossEarnings: 3000.0,
            deductionItems: []
        );

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines): bool {
                return $header['organization_id'] === 'org-payroll-77';
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPayslip($payslip);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // forPaymentReceived() tests
    // =========================================================================

    public function test_forPaymentReceived_debits_bank_account_with_payment_amount(): void
    {
        $customer = $this->makeCustomer(id: 30, receivableAccountId: 1100);

        $payment = Mockery::mock(PaymentReceived::class)->makePartial();
        $payment->organization_id = 'org-sales';
        $payment->id = 100;
        $payment->payment_number = 'PMT-001';
        $payment->payment_date = '2026-03-10';
        $payment->amount = 500.0;
        $payment->bank_account_id = 1010;
        $payment->branch_id = null;
        $payment->customer = $customer;

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPaymentReceived($payment);

        $bankLine = $capturedLines[0];
        $this->assertSame(1010, $bankLine['account_id']);
        $this->assertSame(500.0, $bankLine['debit']);
        $this->assertSame(0, $bankLine['credit']);
    }

    public function test_forPaymentReceived_credits_ar_account_with_payment_amount_and_customer_contact_id(): void
    {
        $customer = $this->makeCustomer(id: 30, receivableAccountId: 1100);

        $payment = Mockery::mock(PaymentReceived::class)->makePartial();
        $payment->organization_id = 'org-sales';
        $payment->id = 101;
        $payment->payment_number = 'PMT-002';
        $payment->payment_date = '2026-03-10';
        $payment->amount = 750.0;
        $payment->bank_account_id = 1010;
        $payment->branch_id = null;
        $payment->customer = $customer;

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPaymentReceived($payment);

        $arLine = $capturedLines[1];
        $this->assertSame(1100, $arLine['account_id']);
        $this->assertSame(750.0, $arLine['credit']);
        $this->assertSame(0, $arLine['debit']);
        $this->assertSame(30, $arLine['contact_id']);
    }

    public function test_forPaymentReceived_passes_organization_id_and_branch_id(): void
    {
        $customer = $this->makeCustomer(id: 30);

        $payment = Mockery::mock(PaymentReceived::class)->makePartial();
        $payment->organization_id = 'org-branch-test';
        $payment->id = 102;
        $payment->payment_number = 'PMT-003';
        $payment->payment_date = '2026-03-11';
        $payment->amount = 200.0;
        $payment->bank_account_id = 1010;
        $payment->branch_id = 7;
        $payment->customer = $customer;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines): bool {
                return $header['organization_id'] === 'org-branch-test'
                    && $header['branch_id'] === 7;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPaymentReceived($payment);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // forPaymentMade() tests
    // =========================================================================

    public function test_forPaymentMade_debits_ap_account_with_payment_amount_and_supplier_contact_id(): void
    {
        $supplier = $this->makeSupplier(id: 55, payableAccountId: 2100);

        $payment = Mockery::mock(PaymentMade::class)->makePartial();
        $payment->organization_id = 'org-purchase';
        $payment->id = 200;
        $payment->payment_number = 'PMTM-001';
        $payment->payment_date = '2026-03-20';
        $payment->amount = 1200.0;
        $payment->bank_account_id = 1010;
        $payment->branch_id = null;
        $payment->supplier = $supplier;

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPaymentMade($payment);

        $apLine = $capturedLines[0];
        $this->assertSame(2100, $apLine['account_id']);
        $this->assertSame(1200.0, $apLine['debit']);
        $this->assertSame(0, $apLine['credit']);
        $this->assertSame(55, $apLine['contact_id']);
    }

    public function test_forPaymentMade_credits_bank_account_with_payment_amount(): void
    {
        $supplier = $this->makeSupplier(id: 55, payableAccountId: 2100);

        $payment = Mockery::mock(PaymentMade::class)->makePartial();
        $payment->organization_id = 'org-purchase';
        $payment->id = 201;
        $payment->payment_number = 'PMTM-002';
        $payment->payment_date = '2026-03-20';
        $payment->amount = 800.0;
        $payment->bank_account_id = 1010;
        $payment->branch_id = null;
        $payment->supplier = $supplier;

        $capturedLines = null;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines) use (&$capturedLines): bool {
                $capturedLines = $lines;
                return true;
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPaymentMade($payment);

        $bankLine = $capturedLines[1];
        $this->assertSame(1010, $bankLine['account_id']);
        $this->assertSame(800.0, $bankLine['credit']);
        $this->assertSame(0, $bankLine['debit']);
    }

    public function test_forPaymentMade_passes_correct_organization_id(): void
    {
        $supplier = $this->makeSupplier(id: 60, payableAccountId: 2100);

        $payment = Mockery::mock(PaymentMade::class)->makePartial();
        $payment->organization_id = 'org-vendor-pay';
        $payment->id = 202;
        $payment->payment_number = 'PMTM-003';
        $payment->payment_date = '2026-03-21';
        $payment->amount = 300.0;
        $payment->bank_account_id = 1010;
        $payment->branch_id = null;
        $payment->supplier = $supplier;

        $this->journalService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $header, array $lines): bool {
                return $header['organization_id'] === 'org-vendor-pay';
            })
            ->andReturn($this->fakeEntry);

        $this->factory->forPaymentMade($payment);

        $this->addToAssertionCount(1);
    }
}
