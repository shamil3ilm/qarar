<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\Payslip;
use App\Services\HR\AttendanceService;
use App\Services\HR\LeaveService;
use App\Services\HR\StatutoryDeductionService;
use App\Services\Print\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeSelfServiceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected LeaveService $leaveService,
        protected StatutoryDeductionService $statutoryService,
        protected PrintService $printService
    ) {}

    /**
     * Get current user's employee profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $employee->load([
            'department',
            'designation',
            'branch',
            'reportingManager',
            'currentSalary.components.salaryComponent',
        ]);

        return $this->success([
            'employee' => $employee,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Get employee's attendance for current month.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $month = $request->get('month', now()->format('Y-m'));
        $startDate = \Carbon\Carbon::parse($month)->startOfMonth();
        $endDate = \Carbon\Carbon::parse($month)->endOfMonth();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date')
            ->get();

        $summary = $this->attendanceService->getEmployeeSummary($employee, $startDate, $endDate);

        return $this->success([
            'month' => $month,
            'records' => $attendance,
            'summary' => $summary,
        ]);
    }

    /**
     * Check in for current day.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $request->validate([
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance = $this->attendanceService->checkIn(
            $employee,
            null,
            Attendance::SOURCE_MOBILE,
            $request->get('latitude') ? (float) $request->get('latitude') : null,
            $request->get('longitude') ? (float) $request->get('longitude') : null,
            $request->get('device_id')
        );

        return $this->success($attendance, 'Checked in successfully at ' . $attendance->check_in->format('H:i'));
    }

    /**
     * Check out for current day.
     */
    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $attendance = $this->attendanceService->checkOut(
            $employee,
            null,
            $request->get('latitude') ? (float) $request->get('latitude') : null,
            $request->get('longitude') ? (float) $request->get('longitude') : null
        );

        return $this->success($attendance, 'Checked out successfully at ' . $attendance->check_out->format('H:i'));
    }

    /**
     * Get employee's leave balances.
     */
    public function myLeaveBalances(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $request->get('year', now()->year))
            ->with('leaveType')
            ->get();

        return $this->success($balances->map(fn($b) => [
            'leave_type' => $b->leaveType->name,
            'leave_type_code' => $b->leaveType->code,
            'entitled' => $b->entitled_days,
            'used' => $b->used_days,
            'pending' => $b->pending_days,
            'available' => $b->available_days,
            'carried_forward' => $b->carried_forward,
        ]));
    }

    /**
     * Get employee's leave requests.
     */
    public function myLeaveRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $query = LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('created_at')
            ->when($request->has('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->has('year'), fn($q) => $q->whereYear('from_date', $request->get('year')));

        $requests = $query->paginate($request->get('per_page', 15));

        return $this->paginated($requests);
    }

    /**
     * Submit a leave request.
     */
    public function submitLeaveRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|string|in:first_half,second_half',
            'reason' => 'required|string|max:1000',
            'emergency_contact' => 'nullable|string|max:100',
        ]);

        // Map to service expected keys
        $validated['from_date'] = $validated['start_date'];
        $validated['to_date'] = $validated['end_date'];
        $validated['is_half_day'] = $validated['half_day'] ?? false;
        unset($validated['start_date'], $validated['end_date'], $validated['half_day']);

        try {
            $leaveRequest = $this->leaveService->createRequest($employee, $validated);

            return $this->created($leaveRequest->load('leaveType'), 'Leave request submitted successfully');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('An unexpected error occurred. Please try again.');
        }
    }

    /**
     * Cancel a leave request.
     */
    public function cancelLeaveRequest(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)
            ->findOrFail($id);

        if (!in_array($leaveRequest->status, ['draft', 'pending'])) {
            return $this->error('Cannot cancel this request.', 'INVALID_STATUS', 400);
        }

        $this->leaveService->cancel($leaveRequest, $request->get('reason', ''));

        return $this->success(null, 'Leave request cancelled.');
    }

    /**
     * Get employee's payslips.
     */
    public function myPayslips(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $query = Payslip::where('employee_id', $employee->id)
            ->with('payrollPeriod')
            ->orderByDesc('created_at')
            ->when($request->has('year'), fn($q) => $q->whereYear('created_at', $request->get('year')));

        $payslips = $query->paginate($request->get('per_page', 12));

        return $this->paginated($payslips);
    }

    /**
     * Get single payslip details.
     */
    public function showPayslip(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $payslip = Payslip::where('employee_id', $employee->id)
            ->with(['items.salaryComponent', 'payrollPeriod', 'employee.department', 'employee.designation'])
            ->findOrFail($id);

        return $this->success($payslip);
    }

    /**
     * Download payslip PDF.
     */
    public function downloadPayslip(Request $request, int $id): Response|JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $payslip = Payslip::where('employee_id', $employee->id)
            ->with(['items.salaryComponent', 'payrollPeriod', 'employee.department', 'employee.designation', 'employee.organization'])
            ->findOrFail($id);

        $pdf = $this->printService->generatePdf('payslip', $payslip, 'a4');

        $filename = "payslip-{$payslip->payslip_number}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Get salary breakdown with statutory deductions preview.
     */
    public function salaryBreakdown(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $salary = $employee->currentSalary;

        if (!$salary) {
            return $this->notFound('No salary structure assigned.');
        }

        $salary->load('components.salaryComponent');

        $grossSalary = $salary->gross_salary;

        // Get statutory deductions preview
        $statutory = $this->statutoryService->calculateDeductions(
            $employee,
            $grossSalary,
            $employee->organization->country_code
        );

        return $this->success([
            'gross_salary' => $grossSalary,
            'currency' => $salary->currency_code,
            'earnings' => $salary->getEarnings()->map(fn($c) => [
                'name' => $c->salaryComponent->name,
                'amount' => $c->amount,
                'is_taxable' => $c->salaryComponent->is_taxable,
            ]),
            'deductions' => $salary->getDeductions()->map(fn($c) => [
                'name' => $c->salaryComponent->name,
                'amount' => $c->amount,
            ]),
            'statutory_deductions' => $statutory['employee_deductions'],
            'employer_contributions' => $statutory['employer_contributions'],
            'summary' => [
                'total_earnings' => $grossSalary,
                'total_deductions' => $salary->getDeductions()->sum('amount') + $statutory['total_employee'],
                'total_statutory' => $statutory['total_employee'],
                'net_salary' => $grossSalary - $salary->getDeductions()->sum('amount') - $statutory['total_employee'],
            ],
        ]);
    }

    /**
     * Get employee's loans.
     */
    public function myLoans(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $loans = \App\Models\HR\EmployeeLoan::where('employee_id', $employee->id)
            ->with('repayments')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($loans->map(fn($loan) => [
            'id' => $loan->id,
            'loan_type' => $loan->loan_type,
            'principal_amount' => $loan->principal_amount,
            'interest_rate' => $loan->interest_rate,
            'total_amount' => $loan->total_amount,
            'emi_amount' => $loan->emi_amount,
            'tenure_months' => $loan->tenure_months,
            'disbursement_date' => $loan->disbursement_date,
            'total_paid' => $loan->repayments->where('status', 'paid')->sum('total_amount'),
            'outstanding' => $loan->outstanding_amount,
            'status' => $loan->status,
            'repayments' => $loan->repayments->map(fn($r) => [
                'due_date' => $r->due_date,
                'amount' => $r->total_amount,
                'status' => $r->status,
                'paid_date' => $r->paid_date,
            ]),
        ]));
    }

    /**
     * Get employee's documents.
     */
    public function myDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return $this->notFound('No employee record found.');
        }

        $documents = \App\Models\HR\EmployeeDocument::where('employee_id', $employee->id)
            ->orderBy('document_type')
            ->get();

        return $this->success($documents->map(fn($doc) => [
            'id' => $doc->id,
            'document_type' => $doc->document_type,
            'document_number' => $doc->document_number,
            'issue_date' => $doc->issue_date,
            'expiry_date' => $doc->expiry_date,
            'is_expired' => $doc->expiry_date ? $doc->expiry_date->isPast() : false,
            'days_to_expiry' => $doc->expiry_date ? now()->diffInDays($doc->expiry_date, false) : null,
        ]));
    }

    /**
     * Get employee directory (colleagues).
     */
    public function directory(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Employee::where('organization_id', $user->organization_id)
            ->where('employment_status', 'active')
            ->with(['department', 'designation', 'branch'])
            ->when($request->has('department_id'), fn($q) => $q->where('department_id', $request->get('department_id')))
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->get('search');
                $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $employees = $query->select([
            'id', 'employee_number', 'first_name', 'last_name',
            'email', 'phone', 'department_id', 'designation_id',
            'branch_id', 'profile_photo_path'
        ])
            ->orderBy('first_name')
            ->paginate($request->get('per_page', 20));

        return $this->paginated($employees);
    }

    /**
     * Get organization holidays.
     */
    public function holidays(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->get('year', now()->year);

        $holidays = \App\Models\HR\Holiday::where('organization_id', $user->organization_id)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return $this->success([
            'year' => $year,
            'holidays' => $holidays,
        ]);
    }
}
