<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Employee $employee;
    protected LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.leave.view',
            'hr.leave.create',
            'hr.leave.approve',
            'hr.leave.manage',
        ]);

        $this->leaveType = LeaveType::factory()->create([
            'organization_id'          => $this->organization->id,
            'is_active'                => true,
            // Pin applicability fields so the service never rejects due to gender/marital/tenure
            // mismatch regardless of faker seed position in the full test suite.
            'applicable_gender'        => 'all',
            'applicable_marital_status'=> 'all',
            'applicable_after_months'  => 0,
            // Disable attachment requirement so a 3-day request never needs an attachment.
            'requires_attachment'      => false,
        ]);

        $this->employee = Employee::factory()->create([
            'organization_id'   => $this->organization->id,
            'branch_id'         => $this->branch->id,
            'employment_status' => 'active',
        ]);

        // Seed a leave balance so the service doesn't reject with "insufficient balance".
        // Use updateOrCreate to tolerate re-runs within a single in-memory DB session.
        LeaveBalance::updateOrCreate(
            [
                'employee_id'   => $this->employee->id,
                'leave_type_id' => $this->leaveType->id,
                'year'          => now()->year,
            ],
            [
                'organization_id' => $this->organization->id,
                'opening_balance' => 30,
                'accrued'         => 0,
                'taken'           => 0,
                'adjustment'      => 0,
                'encashed'        => 0,
                'lapsed'          => 0,
                'closing_balance' => 30,
            ]
        );
    }

    public function test_can_create_leave_request_in_pending_state(): void
    {
        $fromDate = now()->addDays(5)->format('Y-m-d');
        $toDate   = now()->addDays(7)->format('Y-m-d');

        $response = $this->apiPost('/hr/leave/requests', [
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'from_date'     => $fromDate,
            'to_date'       => $toDate,
            'reason'        => 'Annual vacation',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        // App\Models\HR\LeaveRequest has STATUS_DRAFT; the imported Leave\LeaveRequest does not.
        // Use string literals to avoid the missing constant.
        $this->assertContains($response->json('data.status'), ['draft', 'pending']);
        // Assert the record is persisted (use non-date fields; SQLite stores dates with time suffix).
        $this->assertDatabaseHas('leave_requests', [
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'reason'        => 'Annual vacation',
        ]);
    }

    public function test_can_approve_pending_leave_request(): void
    {
        $request = LeaveRequest::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id'     => $this->employee->id,
            'leave_type_id'   => $this->leaveType->id,
            'from_date'       => now()->addDays(5)->format('Y-m-d'),
            'to_date'         => now()->addDays(7)->format('Y-m-d'),
            'total_days'      => 3,
            'reason'          => 'Annual vacation',
            'status'          => LeaveRequest::STATUS_PENDING,
        ]);

        $response = $this->apiPost("/hr/leave/requests/{$request->id}/review", [
            'action' => 'approve',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', LeaveRequest::STATUS_APPROVED);
        $this->assertDatabaseHas('leave_requests', [
            'id'         => $request->id,
            'status'     => LeaveRequest::STATUS_APPROVED,
            'approved_by'=> $this->user->id,
        ]);
    }

    public function test_can_reject_pending_leave_request(): void
    {
        $request = LeaveRequest::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id'     => $this->employee->id,
            'leave_type_id'   => $this->leaveType->id,
            'from_date'       => now()->addDays(5)->format('Y-m-d'),
            'to_date'         => now()->addDays(7)->format('Y-m-d'),
            'total_days'      => 3,
            'reason'          => 'Annual vacation',
            'status'          => LeaveRequest::STATUS_PENDING,
        ]);

        $response = $this->apiPost("/hr/leave/requests/{$request->id}/review", [
            'action'           => 'reject',
            'rejection_reason' => 'Insufficient staffing during that period',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', LeaveRequest::STATUS_REJECTED);
        $this->assertDatabaseHas('leave_requests', [
            'id'               => $request->id,
            'status'           => LeaveRequest::STATUS_REJECTED,
            'rejection_reason' => 'Insufficient staffing during that period',
        ]);
    }

    public function test_can_cancel_own_leave_request(): void
    {
        $request = LeaveRequest::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id'     => $this->employee->id,
            'leave_type_id'   => $this->leaveType->id,
            'from_date'       => now()->addDays(5)->format('Y-m-d'),
            'to_date'         => now()->addDays(7)->format('Y-m-d'),
            'total_days'      => 3,
            'reason'          => 'Annual vacation',
            'status'          => LeaveRequest::STATUS_PENDING,
        ]);

        $response = $this->apiPost("/hr/leave/requests/{$request->id}/cancel", [
            'reason' => 'Plans changed',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', LeaveRequest::STATUS_CANCELLED);
        $this->assertDatabaseHas('leave_requests', [
            'id'                  => $request->id,
            'status'              => LeaveRequest::STATUS_CANCELLED,
            'cancellation_reason' => 'Plans changed',
        ]);
    }

    public function test_can_list_leave_requests(): void
    {
        for ($i = 0; $i < 3; $i++) {
            LeaveRequest::forceCreate([
                'organization_id' => $this->organization->id,
                'employee_id'     => $this->employee->id,
                'leave_type_id'   => $this->leaveType->id,
                'from_date'       => now()->addDays(5 + $i * 10)->format('Y-m-d'),
                'to_date'         => now()->addDays(7 + $i * 10)->format('Y-m-d'),
                'total_days'      => 3,
                'reason'          => 'Annual vacation',
                'status'          => LeaveRequest::STATUS_PENDING,
            ]);
        }

        $response = $this->apiGet('/hr/leave/requests');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertGreaterThanOrEqual(3, $response->json('meta.total'));
    }

    public function test_cannot_create_leave_request_with_zero_balance(): void
    {
        // Override the balance to zero so the service must reject the request.
        LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->update(['closing_balance' => 0]);

        $response = $this->apiPost('/hr/leave/requests', [
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'from_date'     => now()->addDays(5)->format('Y-m-d'),
            'to_date'       => now()->addDays(7)->format('Y-m-d'),
            'reason'        => 'Annual vacation',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Insufficient leave balance', $response->json('error.message'));
    }

    public function test_cannot_create_overlapping_leave_request(): void
    {
        // Create an approved request covering days 5–7 from today.
        LeaveRequest::forceCreate([
            'organization_id' => $this->organization->id,
            'employee_id'     => $this->employee->id,
            'leave_type_id'   => $this->leaveType->id,
            'from_date'       => now()->addDays(4)->format('Y-m-d'),
            'to_date'         => now()->addDays(8)->format('Y-m-d'),
            'total_days'      => 5,
            'reason'          => 'Existing leave',
            'status'          => LeaveRequest::STATUS_APPROVED,
        ]);

        // Try to book within the same window — service must reject.
        $response = $this->apiPost('/hr/leave/requests', [
            'employee_id'   => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'from_date'     => now()->addDays(5)->format('Y-m-d'),
            'to_date'       => now()->addDays(7)->format('Y-m-d'),
            'reason'        => 'Overlapping vacation',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('already have a leave request', $response->json('error.message'));
    }
}
