<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\TrackedJob;

class JobTrackingService
{
    /**
     * Register a new tracked job before dispatching.
     * Returns the TrackedJob record so callers can reference it.
     */
    public function track(
        string  $jobClass,
        array   $payload,
        ?string $jobKey         = null,
        ?int    $organizationId = null,
        ?int    $userId         = null,
    ): TrackedJob {
        return TrackedJob::create([
            'job_class'             => $jobClass,
            'job_key'               => $jobKey,
            'organization_id'       => $organizationId ?? auth()->user()?->organization_id,
            'triggered_by_user_id'  => $userId ?? auth()->id(),
            'payload'               => $payload,
            'status'                => TrackedJob::STATUS_PENDING,
        ]);
    }

    /**
     * Find failed jobs eligible for replay (failed, not yet replayed).
     */
    public function findReplayable(?string $jobClass = null, ?int $orgId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = TrackedJob::failed();

        if ($jobClass) {
            $query->where('job_class', $jobClass);
        }

        if ($orgId) {
            $query->forOrganization($orgId);
        }

        return $query->orderBy('created_at')->get();
    }

    /**
     * Replay a tracked job by re-dispatching it with its original payload.
     */
    public function replay(TrackedJob $tracked): void
    {
        $jobClass = $tracked->job_class;

        if (! class_exists($jobClass)) {
            throw new \RuntimeException("Job class {$jobClass} not found.");
        }

        // Mark original as replayed
        $tracked->update(['status' => TrackedJob::STATUS_REPLAYED]);

        // Create a new tracking record for the replay
        $this->track(
            jobClass:       $jobClass,
            payload:        $tracked->payload,
            jobKey:         $tracked->job_key,
            organizationId: $tracked->organization_id,
            userId:         auth()->id() ?? $tracked->triggered_by_user_id,
        );

        // Dispatch the job
        dispatch(new $jobClass(...array_values($tracked->payload)));
    }
}
