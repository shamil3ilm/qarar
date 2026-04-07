<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PayrollTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/payroll';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * Create an employee associated with the current organization.
     */
    private function createEmployee(array $overrides = []): Employee
    {
        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        return Employee::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'employment_status' => Employee::STATUS_ACTIVE,
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | Unauthenticated Access
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_access_payroll_periods(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}/periods");

        $this->assertUnauthorized($response);
    }

    public function test_unauthenticated_user_cannot_create_payroll_period(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}/periods", []);

        $this->assertUnauthorized($response);
    }

    /*
    |--------------------------------------------------------------------------
    | List Payroll Periods
    |--------------------------------------------------------------------------
    */

    public function test_can_list_payroll_periods(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.view']);

        PayrollPeriod::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/periods");

        $this->assertPaginatedResponse($response);
    }

    public function test_list_payroll_periods_respects_multi_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.view']);

        PayrollPeriod::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create period in another organization
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        PayrollPeriod::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/periods");

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        foreach ($data as $period) {
            $this->assertEquals($this->organization->id, $period['organization_id']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Payroll Period
    |--------------------------------------------------------------------------
    */

    public function test_can_create_payroll_period(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.create']);

        $payload = [
            'name' => 'January 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'payment_date' => '2026-02-05',
        ];

        $response = $this->apiPost("{$this->baseUrl}/periods", $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonPath('data.name', 'January 2026');
        $response->assertJsonPath('data.status', PayrollPeriod::STATUS_OPEN);
    }

    public function test_create_payroll_period_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.create']);

        $response = $this->apiPost("{$this->baseUrl}/periods", []);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['name', 'start_date', 'end_date']);
    }

    public function test_create_payroll_period_validates_end_date_after_start_date(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.create']);

        $payload = [
            'name' => 'Invalid Period',
            'start_date' => '2026-02-01',
            'end_date' => '2026-01-15',
            'payment_date' => '2026-02-10',
        ];

        $response = $this->apiPost("{$this->baseUrl}/periods", $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Process Payroll (Generate Payslips)
    |--------------------------------------------------------------------------
    */

    public function test_can_process_payroll_for_period(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.generate']);

        $employee = $this->createEmployee();

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'is_current' => true,
        ]);

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => PayrollPeriod::STATUS_OPEN,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        $response = $this->apiPost("{$this->baseUrl}/periods/{$period->id}/generate");

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_process_already_closed_payroll_period(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.generate']);

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => PayrollPeriod::STATUS_CLOSED,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/periods/{$period->id}/generate");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Close Payroll Period (Approve)
    |--------------------------------------------------------------------------
    */

    public function test_can_close_processed_payroll_period(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.close']);

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => PayrollPeriod::STATUS_PROCESSED,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/periods/{$period->id}/close");

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.status', PayrollPeriod::STATUS_CLOSED);
    }

    public function test_cannot_close_open_payroll_period(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.close']);

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/periods/{$period->id}/close");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | List Payslips
    |--------------------------------------------------------------------------
    */

    public function test_can_list_payslips(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.view']);

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create separate employees to avoid unique constraint on (payroll_period_id, employee_id)
        for ($i = 0; $i < 3; $i++) {
            $employee = $this->createEmployee();
            Payslip::factory()->create([
                'organization_id' => $this->organization->id,
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ]);
        }

        $response = $this->apiGet("{$this->baseUrl}/payslips");

        $this->assertPaginatedResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Payslip
    |--------------------------------------------------------------------------
    */

    public function test_can_show_payslip(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.view']);

        $employee = $this->createEmployee();

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payslip = Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/payslips/{$payslip->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.id', $payslip->id);
    }

    public function test_cannot_show_payslip_from_another_organization(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.view']);

        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherDept = Department::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherDesig = Designation::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherEmployee = Employee::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'department_id' => $otherDept->id,
            'designation_id' => $otherDesig->id,
        ]);

        $otherPeriod = PayrollPeriod::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $payslip = Payslip::factory()->create([
            'organization_id' => $otherOrg->id,
            'payroll_period_id' => $otherPeriod->id,
            'employee_id' => $otherEmployee->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/payslips/{$payslip->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Payroll Calculation
    |--------------------------------------------------------------------------
    */

    public function test_payslip_contains_earnings_and_deductions(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.view']);

        $employee = $this->createEmployee();

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payslip = Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_earnings' => 10000.00,
            'total_deductions' => 1500.00,
            'net_salary' => 8500.00,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/payslips/{$payslip->id}");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $this->assertEquals(10000.00, (float) $data['gross_earnings']);
        $this->assertEquals(1500.00, (float) $data['total_deductions']);
        $this->assertEquals(8500.00, (float) $data['net_salary']);
    }

    public function test_net_salary_equals_gross_minus_deductions(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.view']);

        $employee = $this->createEmployee();

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $grossEarnings = 15000.00;
        $totalDeductions = 3500.00;
        $expectedNet = $grossEarnings - $totalDeductions;

        $payslip = Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $expectedNet,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/payslips/{$payslip->id}");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $this->assertEquals($expectedNet, (float) $data['net_salary']);
    }

    /*
    |--------------------------------------------------------------------------
    | Payslip Approval
    |--------------------------------------------------------------------------
    */

    public function test_can_approve_payslip(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.approve']);

        $employee = $this->createEmployee();

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payslip = Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => Payslip::STATUS_PENDING,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/payslips/{$payslip->id}/approve");

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.status', Payslip::STATUS_APPROVED);
    }

    public function test_cannot_approve_already_paid_payslip(): void
    {
        $this->setUpAuthenticatedUser(['hr.payroll.approve']);

        $employee = $this->createEmployee();

        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payslip = Payslip::factory()->create([
            'organization_id' => $this->organization->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => Payslip::STATUS_PAID,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/payslips/{$payslip->id}/approve");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Checks
    |--------------------------------------------------------------------------
    */

    public function test_user_without_permission_cannot_create_payroll_period(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiPost("{$this->baseUrl}/periods", [
            'name' => 'Test Period',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        $this->assertForbidden($response);
    }
}
