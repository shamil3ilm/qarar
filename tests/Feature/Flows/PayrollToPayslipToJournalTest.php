<?php

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\Payslip;
use App\Models\HR\EmployeeSalaryComponent;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\SalaryComponent;
use App\Models\HR\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Golden path flow: Payroll Period → Generate Payslips → Submit → Approve → Pay (Journal).
 *
 * Covers the core payroll cycle:
 *   1. Create an employee with current salary assignment
 *   2. Create a payroll period
 *   3. Generate payslips for the period
 *   4. Verify payslip was created for the employee
 *   5. Submit payslip for approval (DRAFT → PENDING)
 *   6. Approve payslip (PENDING → APPROVED)
 *   7. Mark as paid (creates journal entry: Salary expense debit, Payroll payable credit)
 *   8. Verify journal entry balances (double-entry enforcement)
 */
class PayrollToPayslipToJournalTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Employee $employee;
    private Department $department;
    private Designation $designation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'hr.payroll.view',
            'hr.payroll.create',
            'hr.payroll.generate',
            'hr.payroll.approve',
            'hr.payroll.pay',
            'hr.payroll.close',
            'hr.employees.view',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->employee = Employee::factory()->create([
            'organization_id'   => $this->organization->id,
            'branch_id'         => $this->branch->id,
            'department_id'     => $this->department->id,
            'designation_id'    => $this->designation->id,
            'is_active'         => true,
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        // Attach an active salary to the employee — required for generatePayslips()
        $structure = SalaryStructure::factory()->create([
            'organization_id'    => $this->organization->id,
            'currency_code'      => 'SAR',
            'payroll_frequency'  => 'monthly',
        ]);

        $employeeSalary = EmployeeSalary::factory()->current()->create([
            'employee_id'          => $this->employee->id,
            'salary_structure_id'  => $structure->id,
            'gross_salary'         => 10000.00,
            'net_salary'           => 8500.00,
            'ctc'                  => 120000.00,
            'currency_code'        => 'SAR',
        ]);

        // SalaryComponent + EmployeeSalaryComponent needed by PayrollService::calculatePayslipItems()
        // which iterates $salary->getEarnings() — components with SalaryComponent.type = 'earning'
        $basicComponent = SalaryComponent::factory()->create([
            'organization_id'    => $this->organization->id,
            'name'               => 'Basic Salary',
            'code'               => 'BASIC',
            'type'               => 'earning',
            'category'           => 'basic',
            'calculation_type'   => 'fixed',
            'is_taxable'         => true,
            'is_pro_rata'        => true,
            'is_active'          => true,
        ]);

        EmployeeSalaryComponent::factory()->create([
            'employee_salary_id'  => $employeeSalary->id,
            'salary_component_id' => $basicComponent->id,
            'amount'              => 10000.00,
        ]);

        // GL accounts required by PayrollService::createJournalEntry
        $salaryExpenseAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_EXPENSE,
            'sub_type'        => 'operating_expense',
            'code'            => '6000',
            'name'            => 'Salaries & Wages',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $salaryPayableAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => 'other_liability',
            'code'            => '2200',
            'name'            => 'Payroll Payable',
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

        // Wire accounts into config so PayrollService::createJournalEntry() resolves them
        Config::set('erp.default_accounts.salary_expense', $salaryExpenseAccount->id);
        Config::set('erp.default_accounts.salary_payable', $salaryPayableAccount->id);
        Config::set('erp.default_accounts.cash', $bankAccount->id);
    }

    // -------------------------------------------------------------------------
    // Full payroll cycle
    // -------------------------------------------------------------------------

    public function test_full_payroll_generate_approve_pay_flow(): void
    {
        $organization = $this->organization;

        // ----- STEP 1: Create Payroll Period -----
        $periodResponse = $this->apiPost('/hr/payroll/periods', [
            'name'         => 'March 2026',
            'start_date'   => '2026-03-01',
            'end_date'     => '2026-03-31',
            'payment_date' => '2026-04-05',
        ]);

        $periodResponse->assertStatus(201)->assertJson(['success' => true]);
        $periodId     = $periodResponse->json('data.id');
        $periodStatus = $periodResponse->json('data.status');

        $this->assertNotNull($periodId);
        $this->assertEquals(PayrollPeriod::STATUS_OPEN, $periodStatus);

        // ----- STEP 2: Generate Payslips -----
        $generateResponse = $this->apiPost("/hr/payroll/periods/{$periodId}/generate");
        $generateResponse->assertStatus(200)->assertJson(['success' => true]);

        // Payroll period transitions to PROCESSED
        $period = PayrollPeriod::find($periodId);
        $this->assertEquals(PayrollPeriod::STATUS_PROCESSED, $period->status);

        // ----- STEP 3: Verify payslip was created for employee -----
        $payslip = Payslip::where('payroll_period_id', $periodId)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($payslip, 'Payslip should be created for the active employee');
        $this->assertEquals(Payslip::STATUS_DRAFT, $payslip->status);
        $this->assertGreaterThan(0, (float) $payslip->gross_earnings, 'Gross earnings must be positive');
        $this->assertGreaterThan(0, (float) $payslip->net_salary, 'Net salary must be positive');

        // ----- STEP 4: Submit payslip (DRAFT → PENDING) -----
        $submitResponse = $this->apiPost("/hr/payroll/payslips/{$payslip->id}/submit");
        $submitResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Payslip::STATUS_PENDING, $submitResponse->json('data.status'));

        // ----- STEP 5: Approve payslip (PENDING → APPROVED) -----
        $approveResponse = $this->apiPost("/hr/payroll/payslips/{$payslip->id}/approve");
        $approveResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Payslip::STATUS_APPROVED, $approveResponse->json('data.status'));

        // ----- STEP 6: Mark payslip as paid (creates journal entry) -----
        $payResponse = $this->apiPost("/hr/payroll/payslips/{$payslip->id}/pay", [
            'payment_mode'      => 'bank_transfer',
            'payment_reference' => 'SAL-MAR-2026',
        ]);

        $payResponse->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(Payslip::STATUS_PAID, $payResponse->json('data.status'));

        // ----- STEP 7: Verify journal entry was created -----
        $payslip->refresh();
        $this->assertNotNull($payslip->journal_entry_id, 'Journal entry should be created on payment');
        $this->assertNotNull($payslip->paid_at, 'Paid at timestamp must be set');

        $journalEntry = JournalEntry::with('lines')->find($payslip->journal_entry_id);
        $journalLines = JournalEntryLine::where('journal_entry_id', $payslip->journal_entry_id)->get();

        $this->assertGreaterThanOrEqual(2, $journalLines->count(), 'Journal entry must have at least 2 lines');

        // ----- STEP 8: Double-entry validation -----
        $totalDebit  = $journalLines->sum('debit');
        $totalCredit = $journalLines->sum('credit');

        $this->assertEquals(
            round((float) $totalDebit, 2),
            round((float) $totalCredit, 2),
            'Payroll journal entry must balance (debits = credits)'
        );

        // At least one expense debit (salary cost)
        $this->assertTrue(
            $journalLines->contains(fn($l) => (float) $l->debit > 0),
            'Journal entry must have a debit line (salary expense)'
        );

        // At least one credit line (payroll payable or bank)
        $this->assertTrue(
            $journalLines->contains(fn($l) => (float) $l->credit > 0),
            'Journal entry must have a credit line (payable/bank)'
        );

        // organization_id must match the organization
        $this->assertEquals($organization->id, $journalEntry->organization_id);

        // Must have salary expense debit line
        $salaryExpenseLine = $journalEntry->lines->first(fn($l) => (float)$l->debit > 0);
        $this->assertNotNull($salaryExpenseLine, 'Expected salary expense debit line');
        $this->assertEqualsWithDelta((float)$payslip->gross_earnings, (float)$salaryExpenseLine->debit, 0.01);

        // Sum of credits must equal gross earnings
        $totalCredit = $journalEntry->lines->sum('credit');
        $this->assertEqualsWithDelta((float)$payslip->gross_earnings, (float)$totalCredit, 0.01);
    }

    public function test_payroll_journal_organization_id_matches_organization(): void
    {
        // Regression test for HIGH-3: organization_id was missing from journal entries
        // created by PayrollService::createJournalEntry().

        // ----- Create payroll period and generate payslip -----
        $periodResponse = $this->apiPost('/hr/payroll/periods', [
            'name'         => 'Regression Test Period',
            'start_date'   => '2026-03-01',
            'end_date'     => '2026-03-31',
            'payment_date' => '2026-04-05',
        ]);

        $periodResponse->assertStatus(201);
        $periodId = $periodResponse->json('data.id');

        $this->apiPost("/hr/payroll/periods/{$periodId}/generate")->assertStatus(200);

        $payslip = Payslip::where('payroll_period_id', $periodId)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($payslip, 'Payslip should be created for the active employee');

        // ----- Run through submit → approve workflow -----
        $this->apiPost("/hr/payroll/payslips/{$payslip->id}/submit")->assertStatus(200);
        $this->apiPost("/hr/payroll/payslips/{$payslip->id}/approve")->assertStatus(200);

        // ----- Mark as paid — this creates the journal entry -----
        $payResponse = $this->apiPost("/hr/payroll/payslips/{$payslip->id}/pay", [
            'payment_mode'      => 'bank_transfer',
            'payment_reference' => 'SAL-REGRESSION',
        ]);

        $payResponse->assertStatus(200);

        $payslip->refresh();
        $this->assertNotNull($payslip->journal_entry_id, 'Journal entry must be created on pay');

        $journalEntry = JournalEntry::find($payslip->journal_entry_id);
        $this->assertNotNull($journalEntry, 'Journal entry record must exist in database');
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'HIGH-3 regression: payroll journal entry organization_id must match the payroll organization_id'
        );
    }

    public function test_cannot_generate_payslips_for_closed_period(): void
    {
        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => PayrollPeriod::STATUS_CLOSED,
            'start_date'      => '2026-02-01',
            'end_date'        => '2026-02-28',
        ]);

        $response = $this->apiPost("/hr/payroll/periods/{$period->id}/generate");
        $response->assertStatus(422);
    }

    public function test_payslip_status_transitions_are_enforced(): void
    {
        // Create and generate period
        $periodResponse = $this->apiPost('/hr/payroll/periods', [
            'name'         => 'April 2026',
            'start_date'   => '2026-04-01',
            'end_date'     => '2026-04-30',
            'payment_date' => '2026-05-05',
        ]);

        $periodId = $periodResponse->json('data.id');
        $this->apiPost("/hr/payroll/periods/{$periodId}/generate")->assertStatus(200);

        $payslip = Payslip::where('payroll_period_id', $periodId)->first();
        $this->assertNotNull($payslip);

        // Cannot approve a DRAFT payslip directly — must submit first
        $approveBeforeSubmit = $this->apiPost("/hr/payroll/payslips/{$payslip->id}/approve");
        $approveBeforeSubmit->assertStatus(422);

        // Cannot pay a DRAFT payslip
        $payBeforeApprove = $this->apiPost("/hr/payroll/payslips/{$payslip->id}/pay", [
            'payment_mode' => 'cash',
        ]);
        $payBeforeApprove->assertStatus(422);

        // Correct flow: submit → approve → pay
        $this->apiPost("/hr/payroll/payslips/{$payslip->id}/submit")->assertStatus(200);
        $this->apiPost("/hr/payroll/payslips/{$payslip->id}/approve")->assertStatus(200);
        $this->apiPost("/hr/payroll/payslips/{$payslip->id}/pay", [
            'payment_mode' => 'bank_transfer',
        ])->assertStatus(200);

        $payslip->refresh();
        $this->assertEquals(Payslip::STATUS_PAID, $payslip->status);
    }

    public function test_payslip_net_salary_equals_gross_minus_deductions(): void
    {
        $periodResponse = $this->apiPost('/hr/payroll/periods', [
            'name'         => 'May 2026',
            'start_date'   => '2026-05-01',
            'end_date'     => '2026-05-31',
            'payment_date' => '2026-06-05',
        ]);

        $periodId = $periodResponse->json('data.id');
        $this->apiPost("/hr/payroll/periods/{$periodId}/generate")->assertStatus(200);

        $payslip = Payslip::where('payroll_period_id', $periodId)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($payslip);

        $grossEarnings    = (float) $payslip->gross_earnings;
        $totalDeductions  = (float) $payslip->total_deductions;
        $netSalary        = (float) $payslip->net_salary;
        $expectedNet      = max(0, $grossEarnings - $totalDeductions);

        $this->assertEqualsWithDelta(
            $expectedNet,
            $netSalary,
            0.01,
            'Net salary should equal gross earnings minus total deductions'
        );
    }
}
