<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Models\Campaign\UserSegment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SegmentService
{
    public function __construct(private readonly ConditionEvaluator $evaluator)
    {
    }

    public function userMatchesSegment(User $user, UserSegment $segment): bool
    {
        return $this->evaluator->evaluate($segment->conditions ?? [], $user);
    }

    /**
     * Re-evaluate ALL users in an org for a segment, updating memberships and member_count.
     */
    public function reevaluateSegment(UserSegment $segment): void
    {
        $matchingUserIds = [];

        User::where('organization_id', $segment->organization_id)
            ->whereNull('deleted_at')
            ->chunk(500, function ($users) use ($segment, &$matchingUserIds) {
                foreach ($users as $user) {
                    if ($this->userMatchesSegment($user, $segment)) {
                        $matchingUserIds[] = $user->id;
                    }
                }
            });

        DB::transaction(function () use ($segment, $matchingUserIds) {
            // Lock the segment row to prevent concurrent reevaluation races.
            \App\Models\Campaign\UserSegment::lockForUpdate()->findOrFail($segment->id);

            // Remove all current memberships
            DB::table('user_segment_memberships')
                ->where('segment_id', $segment->id)
                ->delete();

            // Insert new memberships in chunks
            $rows = array_map(
                fn (int $userId) => ['user_id' => $userId, 'segment_id' => $segment->id],
                $matchingUserIds
            );

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('user_segment_memberships')->insert($chunk);
            }

            $segment->update([
                'member_count'      => count($matchingUserIds),
                'last_evaluated_at' => now(),
            ]);
        });
    }

    /**
     * Re-evaluate all dynamic segments for an organization.
     */
    public function reevaluateAllForOrganization(int $organizationId): void
    {
        UserSegment::where('organization_id', $organizationId)
            ->where('is_dynamic', true)
            ->whereNull('deleted_at')
            ->each(function (UserSegment $segment) {
                try {
                    $this->reevaluateSegment($segment);
                } catch (\Throwable $e) {
                    Log::error('Failed to reevaluate segment', [
                        'segment_id' => $segment->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            });
    }
}
