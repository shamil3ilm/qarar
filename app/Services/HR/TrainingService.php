<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\TrainingCertification;
use App\Models\HR\TrainingCourse;
use App\Models\HR\TrainingEnrollment;
use App\Models\HR\TrainingNeed;
use App\Models\HR\TrainingSession;
use App\Services\Core\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainingService
{
    // -------------------------------------------------------------------------
    // Courses
    // -------------------------------------------------------------------------

    public function createCourse(array $data, int $userId): TrainingCourse
    {
        return DB::transaction(function () use ($data, $userId): TrainingCourse {
            return TrainingCourse::create(array_merge($data, ['created_by' => $userId]));
        });
    }

    public function updateCourse(TrainingCourse $course, array $data, int $userId): TrainingCourse
    {
        return DB::transaction(function () use ($course, $data): TrainingCourse {
            $course->update($data);

            return $course->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Sessions
    // -------------------------------------------------------------------------

    /**
     * Create a session for a course; auto-generates a session number like TS-YYYY-000001.
     */
    public function createSession(TrainingCourse $course, array $data, int $userId): TrainingSession
    {
        return DB::transaction(function () use ($course, $data, $userId): TrainingSession {
            $sessionNumber = $this->generateSessionNumber($course->organization_id);

            return TrainingSession::create(array_merge($data, [
                'organization_id' => $course->organization_id,
                'course_id'       => $course->id,
                'session_number'  => $sessionNumber,
                'created_by'      => $userId,
            ]));
        });
    }

    public function updateSession(TrainingSession $session, array $data, int $userId): TrainingSession
    {
        return DB::transaction(function () use ($session, $data): TrainingSession {
            $session->update($data);

            return $session->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Enrollments
    // -------------------------------------------------------------------------

    /**
     * Enroll a single employee in a session.
     * Checks available slots and prevents duplicate enrollment.
     */
    public function enroll(TrainingSession $session, int $employeeId, int $userId): TrainingEnrollment
    {
        return DB::transaction(function () use ($session, $employeeId, $userId): TrainingEnrollment {
            // Re-fetch with a row lock to prevent concurrent enrollments from exceeding capacity
            $session = TrainingSession::lockForUpdate()->findOrFail($session->id);

            if (!$session->hasAvailableSlots()) {
                throw new \RuntimeException('No available slots in this training session.');
            }

            $alreadyEnrolled = TrainingEnrollment::where('session_id', $session->id)
                ->where('employee_id', $employeeId)
                ->whereNotIn('status', [TrainingEnrollment::STATUS_CANCELLED])
                ->exists();

            if ($alreadyEnrolled) {
                throw new \RuntimeException('Employee is already enrolled in this session.');
            }

            $enrollment = TrainingEnrollment::create([
                'organization_id' => $session->organization_id,
                'session_id'      => $session->id,
                'employee_id'     => $employeeId,
                'status'          => TrainingEnrollment::STATUS_ENROLLED,
                'enrolled_at'     => now(),
                'enrolled_by'     => $userId,
            ]);

            $session->increment('enrolled_count');

            return $enrollment;
        });
    }

    /**
     * Enroll multiple employees at once.
     * Returns ['enrolled' => [...enrollments], 'skipped' => [...employee_ids with reason]].
     */
    public function bulkEnroll(TrainingSession $session, array $employeeIds, int $userId): array
    {
        return DB::transaction(function () use ($session, $employeeIds, $userId): array {
            $enrolled = [];
            $skipped  = [];

            foreach ($employeeIds as $employeeId) {
                try {
                    $enrollment = $this->enroll($session, (int) $employeeId, $userId);
                    $enrolled[] = $enrollment;
                } catch (\RuntimeException $e) {
                    $skipped[] = [
                        'employee_id' => $employeeId,
                        'reason'      => $e->getMessage(),
                    ];
                }
            }

            return compact('enrolled', 'skipped');
        });
    }

    /**
     * Cancel an enrollment and decrement the session's enrolled count.
     */
    public function cancelEnrollment(TrainingEnrollment $enrollment, int $userId): TrainingEnrollment
    {
        return DB::transaction(function () use ($enrollment): TrainingEnrollment {
            if ($enrollment->status === TrainingEnrollment::STATUS_CANCELLED) {
                throw new \RuntimeException('Enrollment is already cancelled.');
            }

            $session = TrainingSession::lockForUpdate()->findOrFail($enrollment->session_id);

            $enrollment->update(['status' => TrainingEnrollment::STATUS_CANCELLED]);

            // Decrement only if the session counter is still positive
            if ($session->enrolled_count > 0) {
                $session->decrement('enrolled_count');
            }

            return $enrollment->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Session lifecycle
    // -------------------------------------------------------------------------

    public function startSession(TrainingSession $session, int $userId): TrainingSession
    {
        return DB::transaction(function () use ($session, $userId): TrainingSession {
            if ($session->status !== TrainingSession::STATUS_SCHEDULED) {
                throw new \RuntimeException(
                    "Session cannot be started from status '{$session->status}'."
                );
            }

            return $session->start($userId);
        });
    }

    /**
     * Complete a session: mark it completed, process each result, auto-issue certificates for passed enrollments.
     *
     * @param  array  $results  Array of ['employee_id' => int, 'score' => float, 'feedback' => string|null]
     */
    public function completeSession(TrainingSession $session, array $results, int $userId): TrainingSession
    {
        return DB::transaction(function () use ($session, $results, $userId): TrainingSession {
            if (!in_array($session->status, [TrainingSession::STATUS_SCHEDULED, TrainingSession::STATUS_IN_PROGRESS], true)) {
                throw new \RuntimeException(
                    "Session cannot be completed from status '{$session->status}'."
                );
            }

            // Process individual results
            foreach ($results as $result) {
                $employeeId = (int) $result['employee_id'];
                $score      = isset($result['score']) ? (float) $result['score'] : null;
                $feedback   = $result['feedback'] ?? null;

                $enrollment = TrainingEnrollment::where('session_id', $session->id)
                    ->where('employee_id', $employeeId)
                    ->whereNotIn('status', [TrainingEnrollment::STATUS_CANCELLED])
                    ->first();

                if ($enrollment === null) {
                    continue;
                }

                if ($feedback !== null) {
                    $enrollment->feedback = $feedback;
                }

                $passed = $result['passed'] ?? ($score !== null && $score >= 50.0);

                if ($passed) {
                    $enrollment->pass($score ?? 100.0, $userId);

                    // Auto-issue certificate
                    $this->issueCertificate($enrollment, [
                        'issued_by' => $result['issued_by'] ?? null,
                        'notes'     => $result['cert_notes'] ?? null,
                    ], $userId);
                } else {
                    $enrollment->fail($score ?? 0.0, $userId);
                }
            }

            // Mark any remaining enrolled/attended as no_show
            TrainingEnrollment::where('session_id', $session->id)
                ->whereIn('status', [TrainingEnrollment::STATUS_ENROLLED, TrainingEnrollment::STATUS_ATTENDED])
                ->update(['status' => TrainingEnrollment::STATUS_NO_SHOW]);

            return $session->complete($userId);
        });
    }

    // -------------------------------------------------------------------------
    // Certifications
    // -------------------------------------------------------------------------

    /**
     * Issue a certificate for a completed enrollment.
     */
    public function issueCertificate(TrainingEnrollment $enrollment, array $data, int $userId): TrainingCertification
    {
        return DB::transaction(function () use ($enrollment, $data, $userId): TrainingCertification {
            // Avoid duplicate certificates for the same enrollment
            $existing = TrainingCertification::where('enrollment_id', $enrollment->id)
                ->where('is_active', true)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $session = $enrollment->session()->with('course')->first();
            $course  = $session->course;

            $issuedDate = Carbon::today();
            $expiryDate = $course->requiresRecertification()
                ? $issuedDate->copy()->addMonths($course->validity_months)
                : null;

            return TrainingCertification::create([
                'organization_id'    => $enrollment->organization_id,
                'employee_id'        => $enrollment->employee_id,
                'course_id'          => $course->id,
                'enrollment_id'      => $enrollment->id,
                'certificate_number' => $this->generateCertificateNumber($enrollment->organization_id),
                'issued_date'        => $issuedDate->toDateString(),
                'expiry_date'        => $expiryDate?->toDateString(),
                'is_active'          => true,
                'issued_by'          => $data['issued_by'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'created_by'         => $userId,
            ]);
        });
    }

    /**
     * Get certifications expiring within the given number of days.
     */
    public function getExpiringCertifications(int $orgId, int $daysAhead = 30): Collection
    {
        return TrainingCertification::where('organization_id', $orgId)
            ->expiring($daysAhead)
            ->with(['employee', 'course'])
            ->orderBy('expiry_date')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    /**
     * Generate a mandatory-compliance report for the organization.
     * Returns per-employee status of mandatory courses.
     */
    public function getMandatoryComplianceReport(int $orgId): array
    {
        $mandatoryCourses = TrainingCourse::where('organization_id', $orgId)
            ->mandatory()
            ->active()
            ->get();

        if ($mandatoryCourses->isEmpty()) {
            return [
                'mandatory_courses' => [],
                'employees'         => [],
            ];
        }

        $employees = Employee::where('organization_id', $orgId)
            ->where('status', 'active')
            ->with([
                'certifications' => function ($q) use ($orgId): void {
                    $q->where('organization_id', $orgId)->where('is_active', true);
                },
            ])
            ->get();

        $courseIds = $mandatoryCourses->pluck('id')->all();

        $employeeReports = $employees->map(function (Employee $employee) use ($mandatoryCourses, $courseIds): array {
            $employeeCerts = $employee->certifications
                ->whereIn('course_id', $courseIds)
                ->keyBy('course_id');

            $completed = [];
            $pending   = [];
            $overdue   = [];

            foreach ($mandatoryCourses as $course) {
                $cert = $employeeCerts->get($course->id);

                if ($cert === null) {
                    $pending[] = [
                        'course_id'   => $course->id,
                        'course_name' => $course->name,
                        'course_code' => $course->code,
                    ];
                } elseif ($cert->isExpired()) {
                    $overdue[] = [
                        'course_id'   => $course->id,
                        'course_name' => $course->name,
                        'course_code' => $course->code,
                        'expired_on'  => $cert->expiry_date,
                    ];
                } else {
                    $completed[] = [
                        'course_id'          => $course->id,
                        'course_name'        => $course->name,
                        'course_code'        => $course->code,
                        'certificate_number' => $cert->certificate_number,
                        'issued_date'        => $cert->issued_date,
                        'expiry_date'        => $cert->expiry_date,
                        'days_until_expiry'  => $cert->getDaysUntilExpiry(),
                    ];
                }
            }

            return [
                'employee_id'     => $employee->id,
                'employee_number' => $employee->employee_number,
                'name'            => $employee->display_name ?? trim("{$employee->first_name} {$employee->last_name}"),
                'completed'       => $completed,
                'pending'         => $pending,
                'overdue'         => $overdue,
                'compliance_pct'  => $mandatoryCourses->count() > 0
                    ? round(count($completed) / $mandatoryCourses->count() * 100, 1)
                    : 100.0,
            ];
        });

        return [
            'mandatory_courses' => $mandatoryCourses->map(fn($c) => [
                'id'   => $c->id,
                'code' => $c->code,
                'name' => $c->name,
            ])->values()->all(),
            'employees' => $employeeReports->values()->all(),
        ];
    }

    // -------------------------------------------------------------------------
    // Training Needs
    // -------------------------------------------------------------------------

    public function createTrainingNeed(array $data, int $userId): TrainingNeed
    {
        return DB::transaction(function () use ($data, $userId): TrainingNeed {
            return TrainingNeed::create(array_merge($data, ['created_by' => $userId]));
        });
    }

    public function updateTrainingNeed(TrainingNeed $need, array $data, int $userId): TrainingNeed
    {
        return DB::transaction(function () use ($need, $data): TrainingNeed {
            $need->update($data);

            return $need->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Calendar
    // -------------------------------------------------------------------------

    /**
     * Return all sessions in the date range for the given organization.
     */
    public function getTrainingCalendar(int $orgId, string $from, string $to): Collection
    {
        return TrainingSession::where('organization_id', $orgId)
            ->whereBetween('start_date', [$from, $to])
            ->with(['course', 'course.provider'])
            ->orderBy('start_date')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function generateSessionNumber(int $orgId): string
    {
        return app(NumberGeneratorService::class)->generate('TS', '{prefix}-{year}-{number:6}', $orgId);
    }

    private function generateCertificateNumber(int $orgId): string
    {
        return app(NumberGeneratorService::class)->generate('CERT', '{prefix}-{year}-{number:6}', $orgId);
    }
}
