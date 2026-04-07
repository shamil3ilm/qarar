<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Currency;
use App\Models\Accounting\Loan;
use App\Models\Accounting\LoanPayment;
use App\Models\Accounting\LoanSchedule;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class LoanTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/loans';
    private Account $loanAccount;
    private Account $interestAccount;
    private Account $bankGlAccount;
    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->ensureBaseCurrency();
    }

    private function ensureBaseCurrency(): void
    {
        Currency::firstOrCreate(
            ['code' => 'SAR'],
            ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_active' => true]
        );
    }

    /**
     * Set up loan-related GL accounts and bank account.
     */
    private function setUpLoanContext(): void
    {
        $this->loanAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '1200',
            'name' => 'Loans Receivable',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_OTHER_ASSET,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $this->interestAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '4200',
            'name' => 'Interest Income',
            'account_type' => Account::TYPE_INCOME,
            'sub_type' => Account::SUBTYPE_OTHER_INCOME,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $this->bankGlAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Bank Account',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_BANK,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $this->bankAccount = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'bank_name' => 'Al Rajhi Bank',
            'account_name' => 'Main Account',
            'account_number' => '12345678901234',
            'currency_code' => 'SAR',
            'account_type' => BankAccount::TYPE_CURRENT,
            'gl_account_id' => $this->bankGlAccount->id,
            'current_balance' => 500000.00,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Create a loan record.
     */
    private function createLoan(array $overrides = []): Loan
    {
        return Loan::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'loan_type' => Loan::TYPE_EMPLOYEE_LOAN,
            'loan_category' => Loan::CATEGORY_PERSONAL,
            'borrower_name' => 'Ahmed Al-Saud',
            'lender_type' => Loan::LENDER_ORGANIZATION,
            'principal_amount' => 50000.00,
            'interest_rate' => 5.00,
            'interest_type' => Loan::INTEREST_TYPE_FLAT,
            'total_interest' => 2500.00,
            'total_amount' => 52500.00,
            'outstanding_amount' => 52500.00,
            'currency_code' => 'SAR',
            'disbursement_date' => '2025-01-15',
            'first_payment_date' => '2025-02-15',
            'maturity_date' => '2025-12-15',
            'tenure_months' => 12,
            'payment_frequency' => Loan::FREQUENCY_MONTHLY,
            'emi_amount' => 4375.00,
            'total_installments' => 12,
            'paid_installments' => 0,
            'status' => Loan::STATUS_PENDING,
            'approval_status' => Loan::APPROVAL_PENDING,
            'loan_account_id' => $this->loanAccount->id,
            'interest_account_id' => $this->interestAccount->id,
            'bank_account_id' => $this->bankAccount->id,
            'purpose' => 'Personal loan',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    /**
     * Build a valid loan creation payload.
     */
    private function validLoanPayload(array $overrides = []): array
    {
        return array_merge([
            'loan_type' => Loan::TYPE_EMPLOYEE_LOAN,
            'loan_category' => Loan::CATEGORY_PERSONAL,
            'borrower_name' => 'Mohammed Al-Rashid',
            'lender_type' => Loan::LENDER_ORGANIZATION,
            'principal_amount' => 30000.00,
            'interest_rate' => 5.00,
            'interest_type' => Loan::INTEREST_TYPE_FLAT,
            'currency_code' => 'SAR',
            'disbursement_date' => '2025-07-01',
            'first_payment_date' => '2025-08-01',
            'maturity_date' => '2026-06-30',
            'tenure_months' => 12,
            'payment_frequency' => Loan::FREQUENCY_MONTHLY,
            'loan_account_id' => $this->loanAccount->id,
            'interest_account_id' => $this->interestAccount->id,
            'bank_account_id' => $this->bankAccount->id,
            'purpose' => 'Personal loan',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/loans - List Loans
    // -------------------------------------------------------------------------

    public function test_can_list_loans_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $this->createLoan(['borrower_name' => 'Employee A']);
        $this->createLoan(['borrower_name' => 'Employee B']);

        $response = $this->apiGet("{$this->baseUrl}/loans");

        $this->assertSuccessResponse($response);
    }

    public function test_list_loans_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/loans/loans', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_list_loans_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiGet("{$this->baseUrl}/loans");

        $this->assertForbidden($response);
    }

    public function test_list_loans_respects_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $ownLoan = $this->createLoan(['borrower_name' => 'Own Borrower']);

        // Create loan in another organization
        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
            'country_code' => 'AE',
        ]);
        Currency::firstOrCreate(
            ['code' => 'AED'],
            ['name' => 'UAE Dirham', 'symbol' => 'AED', 'decimal_places' => 2, 'is_active' => true]
        );
        $otherLoanAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'code' => '1200',
            'name' => 'Loans',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_OTHER_ASSET,
            'currency_code' => 'AED',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);
        Loan::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'loan_type' => Loan::TYPE_EMPLOYEE_LOAN,
            'borrower_name' => 'Other Org Borrower',
            'lender_type' => Loan::LENDER_ORGANIZATION,
            'principal_amount' => 100000.00,
            'interest_rate' => 3.00,
            'interest_type' => Loan::INTEREST_TYPE_FLAT,
            'total_interest' => 3000.00,
            'total_amount' => 103000.00,
            'outstanding_amount' => 103000.00,
            'currency_code' => 'AED',
            'disbursement_date' => '2025-01-01',
            'first_payment_date' => '2025-02-01',
            'maturity_date' => '2026-01-01',
            'tenure_months' => 12,
            'payment_frequency' => Loan::FREQUENCY_MONTHLY,
            'emi_amount' => 8583.33,
            'total_installments' => 12,
            'paid_installments' => 0,
            'status' => Loan::STATUS_PENDING,
            'approval_status' => Loan::APPROVAL_PENDING,
            'loan_account_id' => $otherLoanAccount->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/loans");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $borrowerNames = collect($data)->pluck('borrower_name')->toArray();
        $this->assertContains('Own Borrower', $borrowerNames);
        $this->assertNotContains('Other Org Borrower', $borrowerNames);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/loans - Create Loan
    // -------------------------------------------------------------------------

    public function test_can_create_loan_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload());

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['borrower_name' => 'Mohammed Al-Rashid']);

        $this->assertDatabaseHas('loans', [
            'organization_id' => $this->organization->id,
            'borrower_name' => 'Mohammed Al-Rashid',
            'status' => Loan::STATUS_PENDING,
        ]);
    }

    public function test_create_loan_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload());

        $this->assertForbidden($response);
    }

    public function test_create_loan_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/loans/loans', [], [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_create_loan_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_loan_validates_principal_amount_positive(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload([
            'principal_amount' => -5000.00,
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_loan_validates_interest_rate_non_negative(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload([
            'interest_rate' => -1.00,
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_loan_validates_maturity_after_disbursement(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload([
            'disbursement_date' => '2026-12-31',
            'maturity_date' => '2025-01-01',
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_created_loan_starts_as_pending(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload());

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['status' => Loan::STATUS_PENDING]);
    }

    public function test_loan_number_is_auto_generated(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload());

        $this->assertCreatedResponse($response);
        $loanNumber = $response->json('data.loan_number');
        $this->assertNotNull($loanNumber);
        $this->assertStringStartsWith('LN-', $loanNumber);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/loans/{loan} - Show Loan
    // -------------------------------------------------------------------------

    public function test_can_show_loan_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $loan = $this->createLoan();

        $response = $this->apiGet("{$this->baseUrl}/loans/{$loan->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'loan_number',
                'loan_type',
                'borrower_name',
                'principal_amount',
                'interest_rate',
                'status',
            ],
        ]);
    }

    public function test_show_loan_returns_404_for_other_org(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
            'country_code' => 'AE',
        ]);
        $otherLoan = Loan::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'loan_type' => Loan::TYPE_EMPLOYEE_LOAN,
            'borrower_name' => 'Other',
            'lender_type' => Loan::LENDER_ORGANIZATION,
            'principal_amount' => 10000.00,
            'interest_rate' => 3.00,
            'interest_type' => Loan::INTEREST_TYPE_FLAT,
            'total_amount' => 10300.00,
            'outstanding_amount' => 10300.00,
            'currency_code' => 'AED',
            'disbursement_date' => '2025-01-01',
            'maturity_date' => '2025-12-31',
            'tenure_months' => 12,
            'payment_frequency' => Loan::FREQUENCY_MONTHLY,
            'total_installments' => 12,
            'paid_installments' => 0,
            'total_interest' => 300.00,
            'first_payment_date' => '2025-02-01',
            'emi_amount' => 858.33,
            'status' => Loan::STATUS_PENDING,
            'approval_status' => Loan::APPROVAL_PENDING,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/loans/{$otherLoan->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/loans/{loan}/outstanding-balance - Outstanding Balance
    // (acts as schedule/balance endpoint)
    // -------------------------------------------------------------------------

    public function test_can_view_loan_outstanding_balance(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $loan = $this->createLoan(['status' => Loan::STATUS_ACTIVE]);

        // Create schedule entries
        for ($i = 1; $i <= 3; $i++) {
            LoanSchedule::create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => "2025-0{$i}-15",
                'principal_amount' => 4166.67,
                'interest_amount' => 208.33,
                'total_amount' => 4375.00,
                'outstanding_balance' => 52500.00 - ($i * 4375.00),
                'status' => LoanSchedule::STATUS_PENDING,
                'paid_amount' => 0,
            ]);
        }

        $response = $this->apiGet("{$this->baseUrl}/loans/{$loan->id}/outstanding-balance");

        $this->assertSuccessResponse($response);
    }

    public function test_outstanding_balance_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);
        $this->setUpLoanContext();

        $loan = $this->createLoan();

        $response = $this->apiGet("{$this->baseUrl}/loans/{$loan->id}/outstanding-balance");

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/loans/{loan}/payments - Record Payment
    // -------------------------------------------------------------------------

    public function test_can_record_loan_payment_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.payment']);
        $this->setUpLoanContext();

        $loan = $this->createLoan(['status' => Loan::STATUS_ACTIVE]);

        $schedule = LoanSchedule::create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => '2025-02-15',
            'principal_amount' => 4166.67,
            'interest_amount' => 208.33,
            'total_amount' => 4375.00,
            'outstanding_balance' => 48125.00,
            'status' => LoanSchedule::STATUS_PENDING,
            'paid_amount' => 0,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/payments", [
            'schedule_id' => $schedule->id,
            'payment_date' => '2025-02-15',
            'principal_paid' => 4166.67,
            'interest_paid' => 208.33,
            'total_paid' => 4375.00,
            'payment_method' => LoanPayment::METHOD_BANK_TRANSFER,
            'reference' => 'PAY-001',
        ]);

        $this->assertCreatedResponse($response);
    }

    public function test_record_payment_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $loan = $this->createLoan(['status' => Loan::STATUS_ACTIVE]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/payments", [
            'payment_date' => '2025-02-15',
            'total_paid' => 4375.00,
            'payment_method' => LoanPayment::METHOD_BANK_TRANSFER,
        ]);

        $this->assertForbidden($response);
    }

    public function test_record_payment_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.payment']);
        $this->setUpLoanContext();

        $loan = $this->createLoan(['status' => Loan::STATUS_ACTIVE]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/payments", []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_record_payment_validates_positive_amount(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.payment']);
        $this->setUpLoanContext();

        $loan = $this->createLoan(['status' => Loan::STATUS_ACTIVE]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/payments", [
            'payment_date' => '2025-02-15',
            'total_paid' => -100.00,
            'payment_method' => LoanPayment::METHOD_BANK_TRANSFER,
        ]);

        $this->assertErrorResponse($response, 422);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/loans/{loan}/approve - Approve Loan
    // (acts as disburse/activate precursor)
    // -------------------------------------------------------------------------

    public function test_can_approve_pending_loan(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.approve']);
        $this->setUpLoanContext();

        $loan = $this->createLoan([
            'status' => Loan::STATUS_PENDING,
            'approval_status' => Loan::APPROVAL_PENDING,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/review", ['action' => 'approve']);

        $this->assertSuccessResponse($response);
        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'approval_status' => Loan::APPROVAL_APPROVED,
        ]);
    }

    public function test_approve_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $loan = $this->createLoan();

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/review", ['action' => 'approve']);

        $this->assertForbidden($response);
    }

    public function test_cannot_approve_already_approved_loan(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.approve']);
        $this->setUpLoanContext();

        $loan = $this->createLoan([
            'status' => Loan::STATUS_APPROVED,
            'approval_status' => Loan::APPROVAL_APPROVED,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/review", ['action' => 'approve']);

        $this->assertErrorResponse($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/loans/{loan}/close - Close Loan
    // -------------------------------------------------------------------------

    public function test_can_close_fully_paid_loan(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.close']);
        $this->setUpLoanContext();

        $loan = $this->createLoan([
            'status' => Loan::STATUS_ACTIVE,
            'outstanding_amount' => 0.00,
            'paid_installments' => 12,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/close");

        $this->assertSuccessResponse($response);
        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => Loan::STATUS_COMPLETED,
        ]);
    }

    public function test_close_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.view']);
        $this->setUpLoanContext();

        $loan = $this->createLoan(['status' => Loan::STATUS_ACTIVE]);

        $response = $this->apiPost("{$this->baseUrl}/loans/{$loan->id}/close");

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // Loan Lifecycle Tests
    // -------------------------------------------------------------------------

    public function test_loan_type_validation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload([
            'loan_type' => 'invalid_type',
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_loan_interest_type_validation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload([
            'interest_type' => 'invalid_interest_type',
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_loan_payment_frequency_validation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.loans.create']);
        $this->setUpLoanContext();

        $response = $this->apiPost("{$this->baseUrl}/loans", $this->validLoanPayload([
            'payment_frequency' => 'invalid_frequency',
        ]));

        $this->assertErrorResponse($response, 422);
    }
}
