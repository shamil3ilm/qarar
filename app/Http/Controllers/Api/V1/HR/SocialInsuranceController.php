<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\SocialInsuranceRecord;
use App\Models\HR\SocialInsuranceScheme;
use App\Models\HR\SocialInsuranceSubmission;
use App\Services\HR\SocialInsuranceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialInsuranceController extends Controller
{
    public function __construct(
        private SocialInsuranceService $siService
    ) {}

    /**
     * List social insurance schemes.
     */
    public function index(Request $request): JsonResponse
    {
        $schemes = SocialInsuranceScheme::query()
            ->when($request->country_code, fn($q, $v) => $q->forCountry($v))
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderBy('country_code')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($schemes);
    }

    /**
     * Create a social insurance scheme.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_code' => 'required|string|max:10',
            'scheme_code' => 'nullable|string|max:20',
            'employee_contribution_pct' => 'numeric|min:0|max:100',
            'employer_contribution_pct' => 'numeric|min:0|max:100',
            'work_hazard_pct' => 'numeric|min:0|max:100',
            'applicable_to' => 'in:all,nationals_only,expats_only',
            'salary_ceiling' => 'nullable|numeric|min:0',
            'salary_floor' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $scheme = SocialInsuranceScheme::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id,
            'is_active' => true,
        ]));

        return $this->created($scheme, 'Social insurance scheme created successfully.');
    }

    /**
     * Show a specific scheme.
     */
    public function show(SocialInsuranceScheme $scheme): JsonResponse
    {
        return $this->success($scheme);
    }

    /**
     * Update a scheme.
     */
    public function update(Request $request, SocialInsuranceScheme $scheme): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'employee_contribution_pct' => 'numeric|min:0|max:100',
            'employer_contribution_pct' => 'numeric|min:0|max:100',
            'work_hazard_pct' => 'numeric|min:0|max:100',
            'applicable_to' => 'in:all,nationals_only,expats_only',
            'salary_ceiling' => 'nullable|numeric|min:0',
            'salary_floor' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $scheme->update($validated);

        return $this->success($scheme->fresh(), 'Scheme updated successfully.');
    }

    /**
     * Enroll an employee in a scheme.
     */
    public function enroll(Request $request, SocialInsuranceScheme $scheme): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'employee_number_si' => 'nullable|string|max:50',
            'enrollment_date' => 'required|date',
            'insurable_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        try {
            $record = $this->siService->enrollEmployee($employee, $scheme, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($record->load('employee', 'scheme'), 'Employee enrolled successfully.');
    }

    /**
     * List employee records for a scheme.
     */
    public function listRecords(Request $request, SocialInsuranceScheme $scheme): JsonResponse
    {
        $records = SocialInsuranceRecord::where('scheme_id', $scheme->id)
            ->with('employee')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($records);
    }

    /**
     * Generate a monthly submission for a scheme.
     */
    public function generateSubmission(Request $request, SocialInsuranceScheme $scheme): JsonResponse
    {
        $validated = $request->validate([
            'period_year' => 'required|integer|min:2000|max:2099',
            'period_month' => 'required|integer|min:1|max:12',
        ]);

        $organization = auth()->user()->load('organization')->organization;

        try {
            $submission = $this->siService->generateMonthlySubmission(
                $organization,
                $scheme,
                $validated['period_year'],
                $validated['period_month']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(
            $submission->load('lines'),
            "Submission generated for {$validated['period_year']}-{$validated['period_month']}."
        );
    }

    /**
     * Show a submission.
     */
    public function showSubmission(SocialInsuranceSubmission $submission): JsonResponse
    {
        return $this->success($submission->load(['scheme', 'lines.employee']));
    }

    /**
     * List submissions.
     */
    public function indexSubmissions(Request $request): JsonResponse
    {
        $submissions = SocialInsuranceSubmission::with('scheme')
            ->when($request->scheme_id, fn($q, $v) => $q->where('scheme_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->year, fn($q, $v) => $q->where('period_year', $v))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($submissions);
    }

    /**
     * Submit a submission.
     */
    public function submitSubmission(Request $request, SocialInsuranceSubmission $submission): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => 'nullable|string|max:100',
        ]);

        return $this->tryAction(
            fn() => $this->siService->submitSubmission($submission, $validated['reference_number'] ?? null),
            'Submission submitted successfully.'
        );
    }
}
