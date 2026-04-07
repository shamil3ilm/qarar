<?php

use App\Http\Controllers\Api\V1\HR\AppraisalReviewController;
use App\Http\Controllers\Api\V1\HR\PersonnelActionController;
use App\Http\Controllers\Api\V1\HR\AttendanceController;
use App\Http\Controllers\Api\V1\HR\TravelExpenseReportController;
use App\Http\Controllers\Api\V1\HR\DepartmentController;
use App\Http\Controllers\Api\V1\HR\DesignationController;
use App\Http\Controllers\Api\V1\HR\EmployeeController;
use App\Http\Controllers\Api\V1\HR\EmployeeDependentController;
use App\Http\Controllers\Api\V1\HR\EmployeeSelfServiceController;
use App\Http\Controllers\Api\V1\HR\EmployeeTransferController;
use App\Http\Controllers\Api\V1\HR\ExitManagementController;
use App\Http\Controllers\Api\V1\HR\HCMOnboardingController;
use App\Http\Controllers\Api\V1\HR\HRDashboardController;
use App\Http\Controllers\Api\V1\HR\HRReportsController;
use App\Http\Controllers\Api\V1\HR\LeaveController;
use App\Http\Controllers\Api\V1\HR\ManagerSelfServiceController;
use App\Http\Controllers\Api\V1\HR\OffCyclePayrollController;
use App\Http\Controllers\Api\V1\HR\OmTaskController;
use App\Http\Controllers\Api\V1\HR\OrgUnitController;
use App\Http\Controllers\Api\V1\HR\PayrollController;
use App\Http\Controllers\Api\V1\HR\PayrollCorrectionController;
use App\Http\Controllers\Api\V1\HR\PerformanceController;
use App\Http\Controllers\Api\V1\HR\ProbationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/hr
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Employee Self-Service (ESS)
    | Employees access only their own data — no extra permission needed beyond auth.
    |--------------------------------------------------------------------------
    */
    Route::prefix('me')->group(function () {
        Route::get('/profile', [EmployeeSelfServiceController::class, 'profile'])->name('hr.ess.profile');
        Route::get('/attendance', [EmployeeSelfServiceController::class, 'myAttendance'])->name('hr.ess.attendance');
        Route::post('/check-in', [EmployeeSelfServiceController::class, 'checkIn'])->name('hr.ess.check-in');
        Route::post('/check-out', [EmployeeSelfServiceController::class, 'checkOut'])->name('hr.ess.check-out');
        Route::get('/leave-balances', [EmployeeSelfServiceController::class, 'myLeaveBalances'])->name('hr.ess.leave-balances');
        Route::get('/leave-requests', [EmployeeSelfServiceController::class, 'myLeaveRequests'])->name('hr.ess.leave-requests');
        Route::post('/leave-requests', [EmployeeSelfServiceController::class, 'submitLeaveRequest'])->name('hr.ess.leave-request.store');
        Route::post('/leave-requests/{id}/cancel', [EmployeeSelfServiceController::class, 'cancelLeaveRequest'])->name('hr.ess.leave-request.cancel');
        Route::get('/payslips', [EmployeeSelfServiceController::class, 'myPayslips'])->name('hr.ess.payslips');
        Route::get('/payslips/{id}', [EmployeeSelfServiceController::class, 'showPayslip'])->name('hr.ess.payslip.show');
        Route::get('/payslips/{id}/download', [EmployeeSelfServiceController::class, 'downloadPayslip'])->name('hr.ess.payslip.download');
        Route::get('/salary-breakdown', [EmployeeSelfServiceController::class, 'salaryBreakdown'])->name('hr.ess.salary-breakdown');
        Route::get('/loans', [EmployeeSelfServiceController::class, 'myLoans'])->name('hr.ess.loans');
        Route::get('/documents', [EmployeeSelfServiceController::class, 'myDocuments'])->name('hr.ess.documents');
    });

    // Employee Directory & Holidays (accessible to all authenticated employees)
    Route::get('/directory', [EmployeeSelfServiceController::class, 'directory'])->name('hr.directory');
    Route::get('/holidays', [EmployeeSelfServiceController::class, 'holidays'])->name('hr.holidays');

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    */
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->middleware('check.permission:hr.departments.view')->name('hr.departments.index');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('check.permission:hr.departments.create')->name('hr.departments.store');
        Route::get('/{department}', [DepartmentController::class, 'show'])->middleware('check.permission:hr.departments.view')->name('hr.departments.show');
        Route::put('/{department}', [DepartmentController::class, 'update'])->middleware('check.permission:hr.departments.edit')->name('hr.departments.update');
        Route::delete('/{department}', [DepartmentController::class, 'destroy'])->middleware('check.permission:hr.departments.delete')->name('hr.departments.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Designations
    |--------------------------------------------------------------------------
    */
    Route::prefix('designations')->group(function () {
        Route::get('/', [DesignationController::class, 'index'])->middleware('check.permission:hr.designations.view')->name('hr.designations.index');
        Route::post('/', [DesignationController::class, 'store'])->middleware('check.permission:hr.designations.create')->name('hr.designations.store');
        Route::get('/{designation}', [DesignationController::class, 'show'])->middleware('check.permission:hr.designations.view')->name('hr.designations.show');
        Route::put('/{designation}', [DesignationController::class, 'update'])->middleware('check.permission:hr.designations.edit')->name('hr.designations.update');
        Route::delete('/{designation}', [DesignationController::class, 'destroy'])->middleware('check.permission:hr.designations.delete')->name('hr.designations.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Employees
    |--------------------------------------------------------------------------
    */
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->middleware('check.permission:hr.employees.view')->name('hr.employees.index');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('check.permission:hr.employees.create')->name('hr.employees.store');
        Route::get('/statistics', [EmployeeController::class, 'statistics'])->middleware('check.permission:hr.employees.view')->name('hr.employees.statistics');
        Route::get('/expiring-documents', [EmployeeController::class, 'expiringDocuments'])->middleware('check.permission:hr.employees.view')->name('hr.employees.expiring-documents');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->middleware('check.permission:hr.employees.view')->name('hr.employees.show');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->middleware('check.permission:hr.employees.edit')->name('hr.employees.update');
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->middleware('check.permission:hr.employees.delete')->name('hr.employees.destroy');
        Route::post('/{employee}/salary', [EmployeeController::class, 'assignSalary'])->middleware('check.permission:hr.employees.salary')->name('hr.employees.assign-salary');
        Route::post('/{employee}/confirm', [EmployeeController::class, 'confirm'])->middleware('check.permission:hr.employees.edit')->name('hr.employees.confirm');
        Route::post('/{employee}/terminate', [EmployeeController::class, 'terminate'])->middleware('check.permission:hr.employees.terminate')->name('hr.employees.terminate');
        Route::post('/{employee}/reactivate', [EmployeeController::class, 'reactivate'])->middleware('check.permission:hr.employees.edit')->name('hr.employees.reactivate');
    });

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->middleware('check.permission:hr.attendance.view')->name('hr.attendance.index');
        Route::post('/check-in', [AttendanceController::class, 'checkIn'])->middleware('check.permission:hr.attendance.manage')->name('hr.attendance.check-in');
        Route::post('/check-out', [AttendanceController::class, 'checkOut'])->middleware('check.permission:hr.attendance.manage')->name('hr.attendance.check-out');
        Route::post('/mark', [AttendanceController::class, 'mark'])->middleware('check.permission:hr.attendance.manage')->name('hr.attendance.mark');
        Route::post('/generate', [AttendanceController::class, 'generate'])->middleware('check.permission:hr.attendance.manage')->name('hr.attendance.generate');
        Route::get('/today-status', [AttendanceController::class, 'todayStatus'])->middleware('check.permission:hr.attendance.view')->name('hr.attendance.today-status');
        Route::get('/employee-summary', [AttendanceController::class, 'employeeSummary'])->middleware('check.permission:hr.attendance.view')->name('hr.attendance.employee-summary');
    });

    /*
    |--------------------------------------------------------------------------
    | Leave Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('leave')->group(function () {
        Route::get('/types', [LeaveController::class, 'leaveTypes'])->name('hr.leave.types');
        Route::get('/requests', [LeaveController::class, 'index'])->middleware('check.permission:hr.leave.view')->name('hr.leave.requests.index');
        Route::post('/requests', [LeaveController::class, 'store'])->middleware('check.permission:hr.leave.create')->name('hr.leave.requests.store');
        Route::get('/requests/{leaveRequest}', [LeaveController::class, 'show'])->middleware('check.permission:hr.leave.view')->name('hr.leave.requests.show');
        Route::post('/requests/{leaveRequest}/submit', [LeaveController::class, 'submit'])->middleware('check.permission:hr.leave.create')->name('hr.leave.requests.submit');
        Route::post('/requests/{leaveRequest}/review', [LeaveController::class, 'review'])->middleware('check.permission:hr.leave.approve')->name('hr.leave.requests.review');
        Route::post('/requests/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->middleware('check.permission:hr.leave.create')->name('hr.leave.requests.cancel');
        Route::get('/balances', [LeaveController::class, 'balances'])->middleware('check.permission:hr.leave.view')->name('hr.leave.balances');
        Route::post('/initialize-balances', [LeaveController::class, 'initializeBalances'])->middleware('check.permission:hr.leave.manage')->name('hr.leave.initialize-balances');
        Route::get('/summary', [LeaveController::class, 'summary'])->middleware('check.permission:hr.leave.view')->name('hr.leave.summary');
    });

    /*
    |--------------------------------------------------------------------------
    | Payroll
    |--------------------------------------------------------------------------
    */
    Route::prefix('payroll')->group(function () {
        // Payroll Periods
        Route::get('/periods', [PayrollController::class, 'periods'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.periods.index');
        Route::post('/periods', [PayrollController::class, 'createPeriod'])->middleware('check.permission:hr.payroll.create')->name('hr.payroll.periods.store');
        Route::get('/periods/{payrollPeriod}', [PayrollController::class, 'showPeriod'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.periods.show');
        Route::post('/periods/{payrollPeriod}/generate', [PayrollController::class, 'generatePayslips'])->middleware(['check.permission:hr.payroll.generate', 'throttle:api-financial', 'simulation'])->name('hr.payroll.periods.generate');
        Route::get('/periods/{payrollPeriod}/summary', [PayrollController::class, 'periodSummary'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.periods.summary');
        Route::post('/periods/{payrollPeriod}/close', [PayrollController::class, 'closePeriod'])->middleware(['check.permission:hr.payroll.close', 'throttle:api-financial'])->name('hr.payroll.periods.close');
        Route::post('/periods/{payrollPeriod}/generate-single', [PayrollController::class, 'generateSinglePayslip'])->middleware(['check.permission:hr.payroll.generate', 'throttle:api-financial'])->name('hr.payroll.periods.generate-single');

        // WPS & GOSI bank-file exports
        Route::get('/periods/{payrollPeriod}/wps-export', [PayrollController::class, 'wpsExport'])->middleware('check.permission:hr.payroll.export')->name('hr.payroll.periods.wps-export');
        Route::get('/periods/{payrollPeriod}/wps-validate', [PayrollController::class, 'wpsValidate'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.periods.wps-validate');
        Route::get('/periods/{payrollPeriod}/gosi-export', [PayrollController::class, 'gosiExport'])->middleware('check.permission:hr.payroll.export')->name('hr.payroll.periods.gosi-export');
        Route::get('/periods/{payrollPeriod}/gosi-validate', [PayrollController::class, 'gosiValidate'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.periods.gosi-validate');

        // Payslips
        Route::get('/payslips', [PayrollController::class, 'payslips'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.payslips.index');
        Route::get('/payslips/{payslip}', [PayrollController::class, 'showPayslip'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.payslips.show');
        Route::post('/payslips/{payslip}/submit', [PayrollController::class, 'submitPayslip'])->middleware('check.permission:hr.payroll.view')->name('hr.payroll.payslips.submit');
        Route::post('/payslips/{payslip}/approve', [PayrollController::class, 'approvePayslip'])->middleware(['check.permission:hr.payroll.approve', 'throttle:api-financial'])->name('hr.payroll.payslips.approve');
        Route::post('/payslips/{payslip}/pay', [PayrollController::class, 'markAsPaid'])->middleware(['check.permission:hr.payroll.pay', 'throttle:api-financial'])->name('hr.payroll.payslips.pay');
        Route::post('/payslips/bulk-approve', [PayrollController::class, 'bulkApprove'])->middleware(['check.permission:hr.payroll.approve', 'throttle:api-financial'])->name('hr.payroll.payslips.bulk-approve');
        Route::post('/payslips/bulk-pay', [PayrollController::class, 'bulkPay'])->middleware(['check.permission:hr.payroll.pay', 'throttle:api-financial'])->name('hr.payroll.payslips.bulk-pay');
    });

    /*
    |--------------------------------------------------------------------------
    | HR Reports & Analytics
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->group(function () {
        Route::get('/dashboard', [HRReportsController::class, 'dashboard'])->middleware('check.permission:hr.reports.view')->name('hr.reports.dashboard');
        Route::get('/headcount', [HRReportsController::class, 'headcount'])->middleware('check.permission:hr.reports.view')->name('hr.reports.headcount');
        Route::get('/turnover', [HRReportsController::class, 'turnover'])->middleware('check.permission:hr.reports.view')->name('hr.reports.turnover');
        Route::get('/attendance', [HRReportsController::class, 'attendance'])->middleware('check.permission:hr.reports.view')->name('hr.reports.attendance');
        Route::get('/leave-analysis', [HRReportsController::class, 'leaveAnalysis'])->middleware('check.permission:hr.reports.view')->name('hr.reports.leave-analysis');
        Route::get('/payroll-summary', [HRReportsController::class, 'payrollSummary'])->middleware('check.permission:hr.payroll.view')->name('hr.reports.payroll-summary');
    });

    /*
    |--------------------------------------------------------------------------
    | Statutory Deductions
    |--------------------------------------------------------------------------
    */
    Route::prefix('statutory')->group(function () {
        Route::get('/config', [HRReportsController::class, 'statutoryConfig'])->middleware('check.permission:hr.payroll.view')->name('hr.statutory.config');
        Route::post('/calculate', [HRReportsController::class, 'calculateStatutory'])->middleware('check.permission:hr.payroll.view')->name('hr.statutory.calculate');
        Route::get('/compliance', [HRReportsController::class, 'statutoryCompliance'])->middleware('check.permission:hr.payroll.view')->name('hr.statutory.compliance');
    });

    /*
    |--------------------------------------------------------------------------
    | HR Dashboard Widgets
    |--------------------------------------------------------------------------
    */
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [HRDashboardController::class, 'index'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.index');
        Route::get('/summary', [HRDashboardController::class, 'summary'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.summary');
        Route::get('/headcount/department', [HRDashboardController::class, 'headcountByDepartment'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.headcount.department');
        Route::get('/headcount/status', [HRDashboardController::class, 'headcountByStatus'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.headcount.status');
        Route::get('/attendance/today', [HRDashboardController::class, 'attendanceToday'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.attendance.today');
        Route::get('/attendance/trend', [HRDashboardController::class, 'attendanceTrend'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.attendance.trend');
        Route::get('/leave', [HRDashboardController::class, 'leaveSummary'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.leave');
        Route::get('/pending-approvals', [HRDashboardController::class, 'pendingApprovals'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.pending-approvals');
        Route::get('/payroll', [HRDashboardController::class, 'payrollSummary'])->middleware('check.permission:hr.payroll.view')->name('hr.dashboard.payroll');
        Route::get('/birthdays', [HRDashboardController::class, 'birthdays'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.birthdays');
        Route::get('/anniversaries', [HRDashboardController::class, 'anniversaries'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.anniversaries');
        Route::get('/document-alerts', [HRDashboardController::class, 'documentAlerts'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.document-alerts');
        Route::get('/new-joiners', [HRDashboardController::class, 'newJoiners'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.new-joiners');
        Route::get('/recent-exits', [HRDashboardController::class, 'recentExits'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.recent-exits');
        Route::get('/demographics', [HRDashboardController::class, 'demographics'])->middleware('check.permission:hr.dashboard.view')->name('hr.dashboard.demographics');
    });

    /*
    |--------------------------------------------------------------------------
    | 360° Appraisal Reviews
    |--------------------------------------------------------------------------
    */
    Route::prefix('appraisals')->group(function (): void {
        Route::post(
            '/{appraisal}/reviewers',
            [AppraisalReviewController::class, 'addReviewers']
        )->middleware('check.permission:hr.appraisals.manage')->name('hr.appraisals.reviewers.store');

        Route::get(
            '/{appraisal}/reviewers',
            [AppraisalReviewController::class, 'listReviewers']
        )->middleware('check.permission:hr.appraisals.view')->name('hr.appraisals.reviewers.index');

        Route::post(
            '/{appraisal}/reviewers/{reviewer}/submit',
            [AppraisalReviewController::class, 'submitReview']
        )->middleware('check.permission:hr.appraisals.review')->name('hr.appraisals.reviewers.submit');

        Route::post(
            '/{appraisal}/reviewers/{reviewer}/decline',
            [AppraisalReviewController::class, 'declineReview']
        )->middleware('check.permission:hr.appraisals.review')->name('hr.appraisals.reviewers.decline');

        Route::get(
            '/{appraisal}/aggregate-ratings',
            [AppraisalReviewController::class, 'aggregateRatings']
        )->middleware('check.permission:hr.appraisals.view')->name('hr.appraisals.aggregate-ratings');
    });

    /*
    |--------------------------------------------------------------------------
    | Off-Cycle Payroll Runs
    |--------------------------------------------------------------------------
    */
    Route::prefix('off-cycle-payroll')->name('hr.off-cycle-payroll.')->group(function () {
        Route::get('/', [OffCyclePayrollController::class, 'index'])->name('index');
        Route::post('/', [OffCyclePayrollController::class, 'store'])->name('store');
        Route::get('/{id}', [OffCyclePayrollController::class, 'show'])->name('show');
        Route::put('/{id}', [OffCyclePayrollController::class, 'update'])->name('update');
        Route::delete('/{id}', [OffCyclePayrollController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/items', [OffCyclePayrollController::class, 'addItem'])->name('items.add');
        Route::delete('/{id}/items/{itemId}', [OffCyclePayrollController::class, 'removeItem'])->name('items.remove');
        Route::post('/{id}/process', [OffCyclePayrollController::class, 'process'])->name('process');
        Route::post('/{id}/cancel', [OffCyclePayrollController::class, 'cancel'])->name('cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Payroll Corrections & Retroactive Accounting
    |--------------------------------------------------------------------------
    */
    Route::prefix('payroll-corrections')->name('hr.payroll-corrections.')->group(function () {
        Route::get('/', [PayrollCorrectionController::class, 'index'])->name('index');
        Route::post('/', [PayrollCorrectionController::class, 'store'])->name('store');
        Route::get('/{id}', [PayrollCorrectionController::class, 'show'])->name('show');
        Route::put('/{id}', [PayrollCorrectionController::class, 'update'])->name('update');
        Route::post('/{id}/approve', [PayrollCorrectionController::class, 'approve'])->name('approve');
        Route::post('/{id}/post', [PayrollCorrectionController::class, 'post'])->name('post');
        Route::post('/{id}/cancel', [PayrollCorrectionController::class, 'cancel'])->name('cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Exit Management & Clearance
    |--------------------------------------------------------------------------
    */
    Route::prefix('exit-management')->name('hr.exit.')->group(function () {
        Route::get('/', [ExitManagementController::class, 'index'])->name('index');
        Route::post('/', [ExitManagementController::class, 'store'])->name('store');
        Route::get('/{id}', [ExitManagementController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [ExitManagementController::class, 'approve'])->name('approve');
        Route::post('/{id}/start-clearance', [ExitManagementController::class, 'startClearance'])->name('start-clearance');
        Route::post('/{id}/clearance-items/{itemId}/clear', [ExitManagementController::class, 'clearItem'])->name('clear-item');
        Route::post('/{id}/complete-clearance', [ExitManagementController::class, 'completeClearance'])->name('complete-clearance');
        Route::post('/{id}/settle', [ExitManagementController::class, 'settle'])->name('settle');
        Route::post('/{id}/close', [ExitManagementController::class, 'close'])->name('close');
    });

    /*
    |--------------------------------------------------------------------------
    | Manager Self-Service Portal
    |--------------------------------------------------------------------------
    */
    Route::prefix('manager')->name('hr.mss.')->group(function () {
        Route::get('/team', [ManagerSelfServiceController::class, 'team'])->name('team');
        Route::get('/pending-approvals', [ManagerSelfServiceController::class, 'pendingApprovals'])->name('pending-approvals');
        Route::get('/team-attendance', [ManagerSelfServiceController::class, 'teamAttendance'])->name('team-attendance');
        Route::get('/team-leave-calendar', [ManagerSelfServiceController::class, 'teamLeaveCalendar'])->name('team-leave-calendar');
        Route::get('/delegations', [ManagerSelfServiceController::class, 'delegations'])->name('delegations');
        Route::post('/delegations', [ManagerSelfServiceController::class, 'createDelegation'])->name('delegations.create');
        Route::delete('/delegations/{delegationId}', [ManagerSelfServiceController::class, 'revokeDelegation'])->name('delegations.revoke');
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Dependents / Family Data (SAP IT 0021)
    |--------------------------------------------------------------------------
    */
    Route::prefix('employees/{employee}')->group(function (): void {
        Route::apiResource('dependents', EmployeeDependentController::class)
            ->names('hr.employees.dependents');
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Transfers (SAP PA40 action code 10)
    |--------------------------------------------------------------------------
    */
    // Static routes BEFORE apiResource to prevent capture by {employeeTransfer} wildcard
    Route::get('employee-transfers/history', [EmployeeTransferController::class, 'employeeHistory'])
        ->name('hr.transfers.history');
    Route::apiResource('employee-transfers', EmployeeTransferController::class)
        ->only(['index', 'store', 'show', 'destroy'])
        ->names('hr.transfers');
    Route::post('employee-transfers/{employeeTransfer}/approve', [EmployeeTransferController::class, 'approve'])
        ->name('hr.transfers.approve');
    Route::post('employee-transfers/{employeeTransfer}/reject', [EmployeeTransferController::class, 'reject'])
        ->name('hr.transfers.reject');
    Route::post('employee-transfers/{employeeTransfer}/apply', [EmployeeTransferController::class, 'apply'])
        ->name('hr.transfers.apply');

    /*
    |--------------------------------------------------------------------------
    | OM Tasks (SAP OM object type T / Relationship A-007)
    |--------------------------------------------------------------------------
    */
    // Static routes BEFORE apiResource to prevent capture by {omTask} wildcard
    Route::get('om-tasks/position-tasks', [OmTaskController::class, 'positionTasks'])
        ->name('hr.om-tasks.position-tasks');
    Route::apiResource('om-tasks', OmTaskController::class)->names('hr.om-tasks');
    Route::post('om-tasks/{omTask}/assign-position', [OmTaskController::class, 'assignToPosition'])
        ->name('hr.om-tasks.assign-position');
    Route::delete('om-tasks/{omTask}/positions/{assignment}', [OmTaskController::class, 'removeFromPosition'])
        ->name('hr.om-tasks.remove-position');

    /*
    |--------------------------------------------------------------------------
    | Org Units (SAP ORGEH — Organisation Object Type O)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('org-units', OrgUnitController::class)->names('hr.org-units');
    Route::get('org-units-hierarchy', [OrgUnitController::class, 'hierarchy'])
        ->name('hr.org-units.hierarchy');
    Route::get('org-units/{orgUnit}/headcount', [OrgUnitController::class, 'headcount'])
        ->name('hr.org-units.headcount');

    /*
    |--------------------------------------------------------------------------
    | Probation Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('probation')->name('hr.probation.')->group(function () {
        Route::get('/', [ProbationController::class, 'index'])->name('index');
        Route::post('/', [ProbationController::class, 'store'])->name('store');
        Route::get('/due-soon', [ProbationController::class, 'dueSoon'])->name('due-soon');
        Route::get('/{id}', [ProbationController::class, 'show'])->name('show');
        Route::put('/{id}', [ProbationController::class, 'update'])->name('update');
        Route::post('/{id}/extend', [ProbationController::class, 'extend'])->name('extend');
        Route::post('/{id}/complete', [ProbationController::class, 'complete'])->name('complete');
        Route::post('/{id}/waive', [ProbationController::class, 'waive'])->name('waive');
    });

    /*
    |--------------------------------------------------------------------------
    | Travel Expense Reports (SAP FI-TV PR05/PR10)
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | HCM Onboarding (SAP Onboarding Cockpit)
    |--------------------------------------------------------------------------
    */
    Route::prefix('onboarding')->name('hr.onboarding.')->group(function () {
        Route::get('/',                                           [HCMOnboardingController::class, 'index'])->name('index');
        Route::post('/',                                          [HCMOnboardingController::class, 'store'])->name('store');
        Route::get('/my-tasks',                                   [HCMOnboardingController::class, 'myTasks'])->name('my-tasks');
        Route::get('/{onboarding}',                               [HCMOnboardingController::class, 'show'])->name('show');
        Route::patch('/{onboarding}',                             [HCMOnboardingController::class, 'update'])->name('update');
        Route::post('/{onboarding}/cancel',                       [HCMOnboardingController::class, 'cancel'])->name('cancel');
        Route::post('/{onboarding}/tasks',                        [HCMOnboardingController::class, 'addTask'])->name('tasks.store');
        Route::patch('/{onboarding}/tasks/{taskId}',              [HCMOnboardingController::class, 'updateTask'])->name('tasks.update');
    });

    Route::prefix('travel')->name('hr.travel.')->group(function () {
        Route::get('requests', [TravelExpenseReportController::class, 'index'])->name('requests.index');
        Route::post('requests', [TravelExpenseReportController::class, 'store'])->name('requests.store');
        Route::get('requests/{travelRequest}', [TravelExpenseReportController::class, 'show'])->name('requests.show');
        Route::post('requests/{uuid}/approve', [TravelExpenseReportController::class, 'approve'])->name('requests.approve');
        Route::get('requests/{uuid}/reports', [TravelExpenseReportController::class, 'indexReports'])->name('reports.index');
        Route::post('requests/{uuid}/reports', [TravelExpenseReportController::class, 'storeReport'])->name('reports.store');
        Route::post('expense-reports/{uuid}/approve', [TravelExpenseReportController::class, 'approveReport'])->name('reports.approve');
        Route::post('expense-reports/{uuid}/post', [TravelExpenseReportController::class, 'postReport'])->name('reports.post');
        Route::get('expense-types', [TravelExpenseReportController::class, 'indexTypes'])->name('expense-types.index');
        Route::post('expense-types', [TravelExpenseReportController::class, 'storeType'])->name('expense-types.store');
    });

    // Personnel Actions — SAP PA40 (hire/transfer/promotion/exit atomic workflow)
    Route::prefix('personnel-actions')->name('hr.personnel-actions.')->group(function () {
        Route::get('/', [PersonnelActionController::class, 'index'])->name('index');
        Route::post('/', [PersonnelActionController::class, 'store'])->name('store');
        Route::get('/{id}', [PersonnelActionController::class, 'show'])->name('show');
        Route::post('/{id}/submit', [PersonnelActionController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [PersonnelActionController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [PersonnelActionController::class, 'reject'])->name('reject');
        Route::post('/{id}/reverse', [PersonnelActionController::class, 'reverse'])->name('reverse');
    });
});
