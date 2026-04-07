<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\TrainingCertification;
use App\Models\HR\TrainingCourse;
use App\Models\HR\TrainingEnrollment;
use App\Models\HR\TrainingNeed;
use App\Models\HR\TrainingProvider;
use App\Models\HR\TrainingSession;
use App\Services\HR\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainingService
    ) {}

    // =========================================================================
    // Providers
    // =========================================================================

    public function indexProviders(Request $request): JsonResponse
    {
        $query = TrainingProvider::query()
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name');

        $providers = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($providers);
    }

    public function storeProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'website'      => 'nullable|url|max:255',
            'is_active'    => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $provider = TrainingProvider::create($validated);

        return $this->created($provider);
    }

    public function showProvider(Request $request, int $id): JsonResponse
    {
        $provider = TrainingProvider::with('courses')->find($id);

        if ($provider === null) {
            return $this->notFound('Training provider not found.');
        }

        return $this->success($provider);
    }

    public function updateProvider(Request $request, int $id): JsonResponse
    {
        $provider = TrainingProvider::find($id);

        if ($provider === null) {
            return $this->notFound('Training provider not found.');
        }

        $validated = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'website'      => 'nullable|url|max:255',
            'is_active'    => 'nullable|boolean',
        ]);

        $provider->update($validated);

        return $this->success($provider->fresh());
    }

    public function destroyProvider(Request $request, int $id): JsonResponse
    {
        $provider = TrainingProvider::find($id);

        if ($provider === null) {
            return $this->notFound('Training provider not found.');
        }

        $provider->delete();

        return $this->success(null, 'Training provider deleted successfully.');
    }

    // =========================================================================
    // Courses
    // =========================================================================

    public function indexCourses(Request $request): JsonResponse
    {
        $query = TrainingCourse::with('provider')
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->when($request->boolean('mandatory_only'), fn($q) => $q->mandatory())
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->delivery_type, fn($q, $v) => $q->where('delivery_type', $v))
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s): void {
                $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
            }))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'code', 'category', 'duration_hours', 'created_at'], 'name'),
                $this->safeSortOrder($request->sort_order)
            );

        $courses = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($courses);
    }

    public function storeCourse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id'           => 'nullable|exists:training_providers,id',
            'code'                  => 'required|string|max:50',
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'category'              => 'required|in:' . implode(',', TrainingCourse::CATEGORIES),
            'delivery_type'         => 'required|in:' . implode(',', TrainingCourse::DELIVERY_TYPES),
            'duration_hours'        => 'nullable|numeric|min:0.5|max:9999',
            'max_participants'      => 'nullable|integer|min:1',
            'is_mandatory'          => 'nullable|boolean',
            'validity_months'       => 'nullable|integer|min:1',
            'cost_per_participant'  => 'nullable|numeric|min:0',
            'currency_code'         => 'nullable|string|size:3',
            'is_active'             => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $course = $this->trainingService->createCourse($validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'COURSE_CREATE_ERROR', 422);
        }

        return $this->created($course->load('provider'));
    }

    public function showCourse(Request $request, int $id): JsonResponse
    {
        $course = TrainingCourse::with(['provider', 'sessions'])->find($id);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        return $this->success($course);
    }

    public function updateCourse(Request $request, int $id): JsonResponse
    {
        $course = TrainingCourse::find($id);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        $validated = $request->validate([
            'provider_id'           => 'nullable|exists:training_providers,id',
            'code'                  => 'sometimes|required|string|max:50',
            'name'                  => 'sometimes|required|string|max:255',
            'description'           => 'nullable|string',
            'category'              => 'sometimes|in:' . implode(',', TrainingCourse::CATEGORIES),
            'delivery_type'         => 'sometimes|in:' . implode(',', TrainingCourse::DELIVERY_TYPES),
            'duration_hours'        => 'nullable|numeric|min:0.5|max:9999',
            'max_participants'      => 'nullable|integer|min:1',
            'is_mandatory'          => 'nullable|boolean',
            'validity_months'       => 'nullable|integer|min:1',
            'cost_per_participant'  => 'nullable|numeric|min:0',
            'currency_code'         => 'nullable|string|size:3',
            'is_active'             => 'nullable|boolean',
        ]);

        try {
            $course = $this->trainingService->updateCourse($course, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'COURSE_UPDATE_ERROR', 422);
        }

        return $this->success($course->load('provider'));
    }

    public function destroyCourse(Request $request, int $id): JsonResponse
    {
        $course = TrainingCourse::find($id);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        $course->delete();

        return $this->success(null, 'Training course deleted successfully.');
    }

    // =========================================================================
    // Sessions
    // =========================================================================

    public function indexSessions(Request $request): JsonResponse
    {
        $query = TrainingSession::with(['course', 'course.provider'])
            ->when($request->course_id, fn($q, $v) => $q->where('course_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->from, fn($q, $v) => $q->where('start_date', '>=', $v))
            ->when($request->to, fn($q, $v) => $q->where('start_date', '<=', $v))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['start_date', 'end_date', 'status', 'session_number'], 'start_date'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $sessions = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($sessions);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'        => 'required|exists:training_courses,id',
            'trainer_name'     => 'nullable|string|max:255',
            'location'         => 'nullable|string|max:255',
            'meeting_link'     => 'nullable|url|max:500',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after:start_date',
            'max_participants' => 'nullable|integer|min:1',
            'notes'            => 'nullable|string',
        ]);

        $course = TrainingCourse::find($validated['course_id']);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        try {
            $session = $this->trainingService->createSession($course, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'SESSION_CREATE_ERROR', 422);
        }

        return $this->created($session->load(['course', 'course.provider']));
    }

    public function showSession(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::with(['course', 'course.provider', 'enrollments.employee'])->find($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        return $this->success($session);
    }

    public function updateSession(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::find($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'trainer_name'     => 'nullable|string|max:255',
            'location'         => 'nullable|string|max:255',
            'meeting_link'     => 'nullable|url|max:500',
            'start_date'       => 'sometimes|required|date',
            'end_date'         => 'sometimes|required|date|after:start_date',
            'max_participants' => 'nullable|integer|min:1',
            'notes'            => 'nullable|string',
        ]);

        try {
            $session = $this->trainingService->updateSession($session, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'SESSION_UPDATE_ERROR', 422);
        }

        return $this->success($session->load(['course', 'course.provider']));
    }

    public function startSession(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::find($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        try {
            $session = $this->trainingService->startSession($session, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'SESSION_START_ERROR', 422);
        }

        return $this->success($session, 'Session started successfully.');
    }

    public function completeSession(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::find($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'results'                   => 'required|array',
            'results.*.employee_id'     => 'required|exists:hr_employees,id',
            'results.*.score'           => 'nullable|numeric|min:0|max:100',
            'results.*.passed'          => 'nullable|boolean',
            'results.*.feedback'        => 'nullable|string',
            'results.*.issued_by'       => 'nullable|string|max:255',
            'results.*.cert_notes'      => 'nullable|string',
        ]);

        try {
            $session = $this->trainingService->completeSession($session, $validated['results'], auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'SESSION_COMPLETE_ERROR', 422);
        }

        return $this->success($session->load('enrollments'), 'Session completed successfully.');
    }

    public function cancelSession(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::find($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        if ($session->status !== TrainingSession::STATUS_SCHEDULED) {
            return $this->error('Only scheduled sessions can be cancelled.', 'SESSION_CANCEL_ERROR', 422);
        }

        $session->update(['status' => TrainingSession::STATUS_CANCELLED]);

        return $this->success($session->fresh(), 'Session cancelled successfully.');
    }

    // =========================================================================
    // Enrollments
    // =========================================================================

    public function enroll(Request $request, int $sessionId): JsonResponse
    {
        $session = TrainingSession::find($sessionId);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
        ]);

        try {
            $enrollment = $this->trainingService->enroll($session, (int) $validated['employee_id'], auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'ENROLLMENT_ERROR', 422);
        }

        return $this->created($enrollment->load('employee'));
    }

    public function bulkEnroll(Request $request, int $sessionId): JsonResponse
    {
        $session = TrainingSession::find($sessionId);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:hr_employees,id',
        ]);

        $result = $this->trainingService->bulkEnroll($session, $validated['employee_ids'], auth()->id());

        return $this->success($result, 'Bulk enrollment processed.');
    }

    public function indexEnrollments(Request $request): JsonResponse
    {
        $query = TrainingEnrollment::with(['session.course', 'employee'])
            ->when($request->session_id, fn($q, $v) => $q->where('session_id', $v))
            ->when($request->employee_id, fn($q, $v) => $q->forEmployee((int) $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->orderBy('enrolled_at', 'desc');

        $enrollments = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($enrollments);
    }

    public function cancelEnrollment(Request $request, int $id): JsonResponse
    {
        $enrollment = TrainingEnrollment::find($id);

        if ($enrollment === null) {
            return $this->notFound('Enrollment not found.');
        }

        try {
            $enrollment = $this->trainingService->cancelEnrollment($enrollment, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'ENROLLMENT_CANCEL_ERROR', 422);
        }

        return $this->success($enrollment, 'Enrollment cancelled successfully.');
    }

    // =========================================================================
    // Certifications
    // =========================================================================

    public function indexCertifications(Request $request): JsonResponse
    {
        $query = TrainingCertification::with(['employee', 'course'])
            ->when($request->employee_id, fn($q, $v) => $q->where('employee_id', $v))
            ->when($request->course_id, fn($q, $v) => $q->where('course_id', $v))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('issued_date', 'desc');

        $certs = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($certs);
    }

    public function storeCertification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id'      => 'nullable|exists:training_enrollments,id',
            'employee_id'        => 'required|exists:hr_employees,id',
            'course_id'          => 'required|exists:training_courses,id',
            'certificate_number' => 'nullable|string|max:100',
            'issued_date'        => 'required|date',
            'expiry_date'        => 'nullable|date|after:issued_date',
            'issued_by'          => 'nullable|string|max:255',
            'notes'              => 'nullable|string',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by']      = auth()->id();
        $validated['is_active']       = true;

        $certification = TrainingCertification::create($validated);

        return $this->created($certification->load(['employee', 'course']));
    }

    public function issueCertificate(Request $request, int $enrollmentId): JsonResponse
    {
        $enrollment = TrainingEnrollment::find($enrollmentId);

        if ($enrollment === null) {
            return $this->notFound('Enrollment not found.');
        }

        $validated = $request->validate([
            'issued_by' => 'nullable|string|max:255',
            'notes'     => 'nullable|string',
        ]);

        try {
            $certification = $this->trainingService->issueCertificate($enrollment, $validated, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'CERTIFICATE_ISSUE_ERROR', 422);
        }

        return $this->created($certification->load(['employee', 'course']));
    }

    public function expiringCertifications(Request $request): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $daysAhead = $request->integer('days', 30);

        $certifications = $this->trainingService->getExpiringCertifications($orgId, $daysAhead);

        return $this->success($certifications);
    }

    // =========================================================================
    // Training Needs
    // =========================================================================

    public function indexNeeds(Request $request): JsonResponse
    {
        $query = TrainingNeed::with(['employee', 'department', 'course'])
            ->when($request->employee_id, fn($q, $v) => $q->where('employee_id', $v))
            ->when($request->department_id, fn($q, $v) => $q->where('department_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->priority, fn($q, $v) => $q->where('priority', $v))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['priority', 'status', 'target_date', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $needs = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($needs);
    }

    public function storeNeed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'   => 'nullable|exists:hr_employees,id',
            'department_id' => 'nullable|exists:hr_departments,id',
            'course_id'     => 'nullable|exists:training_courses,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'priority'      => 'required|in:' . implode(',', TrainingNeed::PRIORITIES),
            'identified_by' => 'nullable|exists:users,id',
            'target_date'   => 'nullable|date',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $need = $this->trainingService->createTrainingNeed($validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'NEED_CREATE_ERROR', 422);
        }

        return $this->created($need->load(['employee', 'department', 'course']));
    }

    public function updateNeed(Request $request, int $id): JsonResponse
    {
        $need = TrainingNeed::find($id);

        if ($need === null) {
            return $this->notFound('Training need not found.');
        }

        $validated = $request->validate([
            'employee_id'   => 'nullable|exists:hr_employees,id',
            'department_id' => 'nullable|exists:hr_departments,id',
            'course_id'     => 'nullable|exists:training_courses,id',
            'title'         => 'sometimes|required|string|max:255',
            'description'   => 'nullable|string',
            'priority'      => 'sometimes|in:' . implode(',', TrainingNeed::PRIORITIES),
            'status'        => 'sometimes|in:' . implode(',', TrainingNeed::STATUSES),
            'identified_by' => 'nullable|exists:users,id',
            'target_date'   => 'nullable|date',
        ]);

        try {
            $need = $this->trainingService->updateTrainingNeed($need, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'NEED_UPDATE_ERROR', 422);
        }

        return $this->success($need->load(['employee', 'department', 'course']));
    }

    public function destroyNeed(Request $request, int $id): JsonResponse
    {
        $need = TrainingNeed::find($id);

        if ($need === null) {
            return $this->notFound('Training need not found.');
        }

        $need->delete();

        return $this->success(null, 'Training need deleted successfully.');
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function mandatoryComplianceReport(Request $request): JsonResponse
    {
        $orgId  = $this->organizationId($request);
        $report = $this->trainingService->getMandatoryComplianceReport($orgId);

        return $this->success($report);
    }

    public function trainingCalendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $orgId    = $this->organizationId($request);
        $sessions = $this->trainingService->getTrainingCalendar($orgId, $validated['from'], $validated['to']);

        return $this->success($sessions);
    }
}
