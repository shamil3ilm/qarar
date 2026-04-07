<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EmployeeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/employees';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    /*
    |--------------------------------------------------------------------------
    | Unauthenticated Access
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_access_employees(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}");

        $this->assertUnauthorized($response);
    }

    public function test_unauthenticated_user_cannot_create_employee(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}", []);

        $this->assertUnauthorized($response);
    }

    /*
    |--------------------------------------------------------------------------
    | List Employees
    |--------------------------------------------------------------------------
    */

    public function test_can_list_employees(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.view']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Employee::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_employees_returns_only_own_organization(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.view']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Employee::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        // Create employee in another organization
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
        Employee::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'department_id' => $otherDept->id,
            'designation_id' => $otherDesig->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        foreach ($data as $employee) {
            $this->assertEquals($this->organization->id, $employee['organization_id']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Employee
    |--------------------------------------------------------------------------
    */

    public function test_can_create_employee(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.create']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payload = [
            'employee_number' => 'EMP-001',
            'first_name' => 'Ahmed',
            'last_name' => 'Al-Rashid',
            'email' => 'ahmed.rashid@example.com',
            'phone' => '+966501234567',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'joining_date' => '2026-01-15',
            'employment_type' => Employee::EMPLOYMENT_TYPE_FULL_TIME,
            'gender' => 'male',
            'nationality' => 'SA',
            'country_code' => 'SA',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonPath('data.employee_number', 'EMP-001');
        $response->assertJsonPath('data.first_name', 'Ahmed');
        $response->assertJsonPath('data.last_name', 'Al-Rashid');
        $response->assertJsonPath('data.employment_status', Employee::STATUS_ACTIVE);
    }

    public function test_create_employee_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.create']);

        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors([
            'first_name',
            'last_name',
            'email',
            'department_id',
            'designation_id',
            'joining_date',
        ]);
    }

    public function test_create_employee_validates_unique_employee_code(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.create']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create existing employee with the same code
        Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'employee_number' => 'EMP-DUP',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $payload = [
            'employee_number' => 'EMP-DUP',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'joining_date' => '2026-02-01',
            'employment_type' => Employee::EMPLOYMENT_TYPE_FULL_TIME,
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['employee_number']);
    }

    public function test_create_employee_validates_unique_email(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.create']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'email' => 'duplicate@example.com',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $payload = [
            'employee_number' => 'EMP-NEW',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'duplicate@example.com',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'joining_date' => '2026-02-01',
            'employment_type' => Employee::EMPLOYMENT_TYPE_FULL_TIME,
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['email']);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Employee
    |--------------------------------------------------------------------------
    */

    public function test_can_show_employee(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.view']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$employee->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.id', $employee->id);
    }

    public function test_cannot_show_employee_from_another_organization(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.view']);

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

        $employee = Employee::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'department_id' => $otherDept->id,
            'designation_id' => $otherDesig->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$employee->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Employee
    |--------------------------------------------------------------------------
    */

    public function test_can_update_employee(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.edit']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $newDesignation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Senior Engineer',
        ]);

        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $payload = [
            'designation_id' => $newDesignation->id,
            'phone' => '+966509876543',
            'notes' => 'Promoted to Senior Engineer',
        ];

        $response = $this->apiPut("{$this->baseUrl}/{$employee->id}", $payload);

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.designation_id', $newDesignation->id);
    }

    public function test_update_employee_validates_unique_employee_code(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.edit']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $employee1 = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'employee_number' => 'EMP-001',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $employee2 = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'employee_number' => 'EMP-002',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$employee2->id}", [
            'employee_number' => 'EMP-001',
        ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['employee_number']);
    }

    /*
    |--------------------------------------------------------------------------
    | Deactivate (Soft Delete) Employee
    |--------------------------------------------------------------------------
    */

    public function test_can_deactivate_employee(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.delete']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$employee->id}");

        $this->assertSuccessResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Employee Code Uniqueness
    |--------------------------------------------------------------------------
    */

    public function test_employee_code_is_unique_within_organization(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.create']);

        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'employee_number' => 'EMP-UNIQUE',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);

        $payload = [
            'employee_number' => 'EMP-UNIQUE',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test.unique@example.com',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'joining_date' => '2026-02-01',
            'employment_type' => Employee::EMPLOYMENT_TYPE_FULL_TIME,
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['employee_number']);
    }

    public function test_employee_code_can_be_reused_across_organizations(): void
    {
        $this->setUpAuthenticatedUser(['hr.employees.create']);

        // Create employee with code in another organization
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

        Employee::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'employee_number' => 'EMP-CROSS-ORG',
            'department_id' => $otherDept->id,
            'designation_id' => $otherDesig->id,
        ]);

        // Same code in current organization should be allowed
        $department = Department::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $designation = Designation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payload = [
            'employee_number' => 'EMP-CROSS-ORG',
            'first_name' => 'Cross',
            'last_name' => 'Org',
            'email' => 'cross.org@example.com',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'joining_date' => '2026-02-01',
            'employment_type' => Employee::EMPLOYMENT_TYPE_FULL_TIME,
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Checks
    |--------------------------------------------------------------------------
    */

    public function test_user_without_permission_cannot_create_employee(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiPost($this->baseUrl, [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

        $this->assertForbidden($response);
    }
}
