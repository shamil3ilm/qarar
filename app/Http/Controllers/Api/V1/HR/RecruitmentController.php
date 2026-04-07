<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Candidate;
use App\Models\HR\InterviewSchedule;
use App\Models\HR\JobApplication;
use App\Models\HR\JobOffer;
use App\Models\HR\JobPosting;
use App\Services\HR\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RecruitmentController extends Controller
{
    public function __construct(
        private readonly RecruitmentService $recruitmentService
    ) {}

    // =========================================================================
    // Job Postings
    // =========================================================================

    public function indexJobPostings(Request $request): JsonResponse
    {
        $query = JobPosting::with(['department', 'designation', 'creator'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->department_id, fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->employment_type, fn ($q, $v) => $q->where('employment_type', $v))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['title', 'status', 'posted_at', 'closes_at', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function storeJobPosting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id'       => 'nullable|integer|exists:branches,id',
            'department_id'   => 'nullable|integer|exists:hr_departments,id',
            'designation_id'  => 'nullable|integer|exists:hr_designations,id',
            'title'           => 'required|string|max:200',
            'description'     => 'required|string',
            'requirements'    => 'nullable|string',
            'employment_type' => ['nullable', Rule::in([
                JobPosting::EMPLOYMENT_TYPE_FULL_TIME,
                JobPosting::EMPLOYMENT_TYPE_PART_TIME,
                JobPosting::EMPLOYMENT_TYPE_CONTRACT,
                JobPosting::EMPLOYMENT_TYPE_INTERN,
            ])],
            'location'        => 'nullable|string|max:200',
            'salary_min'      => 'nullable|numeric|min:0',
            'salary_max'      => 'nullable|numeric|min:0|gte:salary_min',
            'currency_code'   => 'nullable|string|size:3',
            'vacancies'       => 'nullable|integer|min:1',
            'closes_at'       => 'nullable|date|after:today',
        ]);

        $posting = $this->recruitmentService->createJobPosting($validated, auth()->id());

        return $this->created($posting->load(['department', 'designation']));
    }

    public function showJobPosting(int $id): JsonResponse
    {
        $posting = JobPosting::with(['department', 'designation', 'creator'])
            ->findOrFail($id);

        return $this->success($posting);
    }

    public function updateJobPosting(Request $request, int $id): JsonResponse
    {
        $posting = JobPosting::findOrFail($id);

        $validated = $request->validate([
            'branch_id'       => 'nullable|integer|exists:branches,id',
            'department_id'   => 'nullable|integer|exists:hr_departments,id',
            'designation_id'  => 'nullable|integer|exists:hr_designations,id',
            'title'           => 'sometimes|required|string|max:200',
            'description'     => 'sometimes|required|string',
            'requirements'    => 'nullable|string',
            'employment_type' => ['nullable', Rule::in([
                JobPosting::EMPLOYMENT_TYPE_FULL_TIME,
                JobPosting::EMPLOYMENT_TYPE_PART_TIME,
                JobPosting::EMPLOYMENT_TYPE_CONTRACT,
                JobPosting::EMPLOYMENT_TYPE_INTERN,
            ])],
            'location'        => 'nullable|string|max:200',
            'salary_min'      => 'nullable|numeric|min:0',
            'salary_max'      => 'nullable|numeric|min:0|gte:salary_min',
            'currency_code'   => 'nullable|string|size:3',
            'vacancies'       => 'nullable|integer|min:1',
            'closes_at'       => 'nullable|date',
        ]);

        $posting = $this->recruitmentService->updateJobPosting($posting, $validated);

        return $this->success($posting->load(['department', 'designation']));
    }

    public function destroyJobPosting(int $id): JsonResponse
    {
        $posting = JobPosting::findOrFail($id);
        $posting->delete();

        return $this->success(null, 'Job posting deleted successfully.');
    }

    public function publishJobPosting(int $id): JsonResponse
    {
        $posting = JobPosting::findOrFail($id);

        try {
            $posting = $this->recruitmentService->publishJobPosting($posting, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success($posting, 'Job posting published successfully.');
    }

    public function closeJobPosting(int $id): JsonResponse
    {
        $posting = JobPosting::findOrFail($id);
        $posting = $this->recruitmentService->closeJobPosting($posting, auth()->id());

        return $this->success($posting, 'Job posting closed successfully.');
    }

    // =========================================================================
    // Candidates
    // =========================================================================

    public function indexCandidates(Request $request): JsonResponse
    {
        $query = Candidate::with(['applications'])
            ->when($request->source, fn ($q, $v) => $q->where('source', $v))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('current_company', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['first_name', 'last_name', 'email', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function storeCandidate(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $validated = $request->validate([
            'first_name'              => 'required|string|max:100',
            'last_name'               => 'required|string|max:100',
            'email'                   => [
                'required',
                'email',
                'max:200',
                Rule::unique('candidates', 'email')->where('organization_id', $organizationId),
            ],
            'phone'                   => 'nullable|string|max:50',
            'linkedin_url'            => 'nullable|url|max:500',
            'resume_path'             => 'nullable|string|max:500',
            'total_experience_years'  => 'nullable|numeric|min:0|max:50',
            'current_company'         => 'nullable|string|max:200',
            'current_title'           => 'nullable|string|max:200',
            'source'                  => ['nullable', Rule::in([
                Candidate::SOURCE_JOB_BOARD,
                Candidate::SOURCE_REFERRAL,
                Candidate::SOURCE_LINKEDIN,
                Candidate::SOURCE_DIRECT,
                Candidate::SOURCE_AGENCY,
                Candidate::SOURCE_OTHER,
            ])],
            'notes' => 'nullable|string',
        ]);

        $candidate = $this->recruitmentService->createCandidate($validated, auth()->id());

        return $this->created($candidate);
    }

    public function showCandidate(int $id): JsonResponse
    {
        $candidate = Candidate::with(['applications.jobPosting'])->findOrFail($id);

        return $this->success($candidate);
    }

    public function updateCandidate(Request $request, int $id): JsonResponse
    {
        $candidate = Candidate::findOrFail($id);
        $organizationId = auth()->user()->organization_id;

        $validated = $request->validate([
            'first_name'             => 'sometimes|required|string|max:100',
            'last_name'              => 'sometimes|required|string|max:100',
            'email'                  => [
                'sometimes',
                'required',
                'email',
                'max:200',
                Rule::unique('candidates', 'email')
                    ->where('organization_id', $organizationId)
                    ->ignore($candidate->id),
            ],
            'phone'                  => 'nullable|string|max:50',
            'linkedin_url'           => 'nullable|url|max:500',
            'resume_path'            => 'nullable|string|max:500',
            'total_experience_years' => 'nullable|numeric|min:0|max:50',
            'current_company'        => 'nullable|string|max:200',
            'current_title'          => 'nullable|string|max:200',
            'source'                 => ['nullable', Rule::in([
                Candidate::SOURCE_JOB_BOARD,
                Candidate::SOURCE_REFERRAL,
                Candidate::SOURCE_LINKEDIN,
                Candidate::SOURCE_DIRECT,
                Candidate::SOURCE_AGENCY,
                Candidate::SOURCE_OTHER,
            ])],
            'notes' => 'nullable|string',
        ]);

        $candidate = $this->recruitmentService->updateCandidate($candidate, $validated);

        return $this->success($candidate);
    }

    // =========================================================================
    // Applications
    // =========================================================================

    public function indexApplications(Request $request): JsonResponse
    {
        $query = JobApplication::with(['jobPosting', 'candidate', 'reviewer'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->job_posting_id, fn ($q, $v) => $q->where('job_posting_id', $v))
            ->when($request->candidate_id, fn ($q, $v) => $q->where('candidate_id', $v))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['applied_at', 'status', 'created_at'], 'applied_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function showApplication(int $id): JsonResponse
    {
        $application = JobApplication::with([
            'jobPosting',
            'candidate',
            'interviews',
            'offer',
            'reviewer',
        ])->findOrFail($id);

        return $this->success($application);
    }

    public function applyForJob(Request $request, int $jobPostingId): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id'       => 'required|integer|exists:candidates,id',
            'cover_letter'       => 'nullable|string',
            'expected_salary'    => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0',
        ]);

        $validated['job_posting_id'] = $jobPostingId;

        try {
            $application = $this->recruitmentService->applyForJob($validated, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->created($application->load(['jobPosting', 'candidate']));
    }

    public function shortlistApplication(int $id): JsonResponse
    {
        $application = JobApplication::findOrFail($id);

        try {
            $application = $this->recruitmentService->shortlistApplication($application, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success($application, 'Application shortlisted successfully.');
    }

    public function rejectApplication(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $application = JobApplication::findOrFail($id);

        try {
            $application = $this->recruitmentService->rejectApplication(
                $application,
                $validated['reason'],
                auth()->id()
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success($application, 'Application rejected.');
    }

    public function convertToEmployee(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::findOrFail($id);

        $validated = $request->validate([
            'employee_number'     => 'nullable|string|max:50',
            'department_id'       => 'nullable|integer|exists:hr_departments,id',
            'designation_id'      => 'nullable|integer|exists:hr_designations,id',
            'branch_id'           => 'nullable|integer|exists:branches,id',
            'joining_date'        => 'nullable|date',
            'employment_type'     => 'nullable|string|max:50',
            'employment_status'   => 'nullable|string|max:50',
        ]);

        try {
            $employee = $this->recruitmentService->convertToEmployee($application, $validated, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->created($employee, 'Candidate converted to employee successfully.');
    }

    // =========================================================================
    // Interviews
    // =========================================================================

    public function scheduleInterview(Request $request, int $applicationId): JsonResponse
    {
        $validated = $request->validate([
            'interview_type'   => ['required', Rule::in([
                InterviewSchedule::TYPE_PHONE,
                InterviewSchedule::TYPE_VIDEO,
                InterviewSchedule::TYPE_IN_PERSON,
                InterviewSchedule::TYPE_TECHNICAL,
                InterviewSchedule::TYPE_PANEL,
            ])],
            'scheduled_at'     => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'location'         => 'nullable|string|max:300',
            'meeting_link'     => 'nullable|url|max:500',
            'interviewers'     => 'nullable|array',
            'interviewers.*'   => 'integer|exists:users,id',
        ]);

        $validated['job_application_id'] = $applicationId;

        try {
            $interview = $this->recruitmentService->scheduleInterview($validated, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->created($interview->load('application'), 'Interview scheduled successfully.');
    }

    public function recordInterviewFeedback(Request $request, int $interviewId): JsonResponse
    {
        $interview = InterviewSchedule::findOrFail($interviewId);

        $validated = $request->validate([
            'feedback'       => 'required|string',
            'rating'         => 'nullable|integer|min:1|max:5',
            'recommendation' => ['nullable', Rule::in([
                'strong_yes',
                'yes',
                'neutral',
                'no',
                'strong_no',
            ])],
        ]);

        $interview = $this->recruitmentService->recordInterviewFeedback($interview, $validated, auth()->id());

        return $this->success($interview, 'Interview feedback recorded.');
    }

    // =========================================================================
    // Offers
    // =========================================================================

    public function createJobOffer(Request $request, int $applicationId): JsonResponse
    {
        $validated = $request->validate([
            'offered_salary'     => 'required|numeric|min:0',
            'currency_code'      => 'nullable|string|size:3',
            'joining_date'       => 'nullable|date',
            'offer_valid_until'  => 'nullable|date|after:today',
            'terms'              => 'nullable|string',
        ]);

        $validated['job_application_id'] = $applicationId;

        try {
            $offer = $this->recruitmentService->createJobOffer($validated, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->created($offer->load(['candidate', 'jobPosting']), 'Job offer created successfully.');
    }

    public function sendOffer(int $offerId): JsonResponse
    {
        $offer = JobOffer::findOrFail($offerId);

        try {
            $offer = $this->recruitmentService->sendOffer($offer, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success($offer, 'Job offer sent successfully.');
    }

    public function acceptOffer(int $offerId): JsonResponse
    {
        $offer = JobOffer::findOrFail($offerId);

        try {
            $offer = $this->recruitmentService->acceptOffer($offer, auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success($offer, 'Offer accepted successfully.');
    }

    public function declineOffer(Request $request, int $offerId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $offer = JobOffer::findOrFail($offerId);

        try {
            $offer = $this->recruitmentService->declineOffer($offer, $validated['reason'], auth()->id());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->success($offer, 'Offer declined.');
    }
}
