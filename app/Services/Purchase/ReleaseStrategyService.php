<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\ReleaseStrategy;
use App\Models\Purchase\ReleaseStrategyApproval;
use App\Models\Purchase\ReleaseStrategyLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReleaseStrategyService
{
    /**
     * Find the active release strategy that applies to the given document type and amount.
     * Strategies whose levels all have amount ranges will be matched; strategies without
     * amount constraints on any level match universally.
     */
    public function getApplicableStrategy(string $docType, float $amount): ?ReleaseStrategy
    {
        $strategies = ReleaseStrategy::with('levels')
            ->active()
            ->forDocument($docType)
            ->get();

        foreach ($strategies as $strategy) {
            $levels = $strategy->levels;

            if ($levels->isEmpty()) {
                continue;
            }

            // A strategy is applicable when at least one of its levels has an amount range
            // that matches, OR when no levels have any amount constraints at all.
            $hasAmountConstraints = $levels->filter(
                fn (ReleaseStrategyLevel $l) => $l->min_amount !== null || $l->max_amount !== null
            )->isNotEmpty();

            if (! $hasAmountConstraints) {
                // No amount filtering — strategy applies universally.
                return $strategy;
            }

            $anyLevelMatches = $levels->filter(
                fn (ReleaseStrategyLevel $l) => $l->appliesToAmount($amount)
            )->isNotEmpty();

            if ($anyLevelMatches) {
                return $strategy;
            }
        }

        return null;
    }

    /**
     * Initiate the release workflow for a document.
     * Creates one ReleaseStrategyApproval row per level, all with status=pending.
     * Returns the created approval rows.
     *
     * @return ReleaseStrategyApproval[]
     */
    public function initiate(string $docType, int $docId, float $amount): array
    {
        $strategy = $this->getApplicableStrategy($docType, $amount);

        if (! $strategy) {
            return [];
        }

        return DB::transaction(function () use ($strategy, $docType, $docId): array {
            $created = [];

            foreach ($strategy->levels as $level) {
                $created[] = ReleaseStrategyApproval::create([
                    'release_strategy_id' => $strategy->id,
                    'level_id'            => $level->id,
                    'document_type'       => $docType,
                    'document_id'         => $docId,
                    'status'              => ReleaseStrategyApproval::STATUS_PENDING,
                ]);
            }

            return $created;
        });
    }

    /**
     * Get the current (lowest-level) pending approval for a document.
     */
    public function getCurrentLevel(string $docType, int $docId): ?ReleaseStrategyApproval
    {
        return ReleaseStrategyApproval::with('level')
            ->where('document_type', $docType)
            ->where('document_id', $docId)
            ->where('status', ReleaseStrategyApproval::STATUS_PENDING)
            ->join('release_strategy_levels', 'release_strategy_approvals.level_id', '=', 'release_strategy_levels.id')
            ->orderBy('release_strategy_levels.level', 'asc')
            ->select('release_strategy_approvals.*')
            ->first();
    }

    /**
     * Approve or record a decision on a single release approval level.
     * Returns true when the document is fully released (all levels approved).
     */
    public function approve(ReleaseStrategyApproval $approval, User $approver, ?string $comments): bool
    {
        if (! $approval->isPending()) {
            throw new \InvalidArgumentException('This approval has already been acted upon.');
        }

        $approval->update([
            'status'      => ReleaseStrategyApproval::STATUS_APPROVED,
            'approver_id' => $approver->id,
            'comments'    => $comments,
            'acted_at'    => now(),
        ]);

        return $this->isFullyReleased($approval->document_type, $approval->document_id);
    }

    /**
     * Reject a release approval level.
     */
    public function reject(ReleaseStrategyApproval $approval, User $approver, ?string $comments): void
    {
        if (! $approval->isPending()) {
            throw new \InvalidArgumentException('This approval has already been acted upon.');
        }

        $approval->update([
            'status'      => ReleaseStrategyApproval::STATUS_REJECTED,
            'approver_id' => $approver->id,
            'comments'    => $comments,
            'acted_at'    => now(),
        ]);
    }

    /**
     * Check whether all approval levels for a document are STATUS_APPROVED.
     */
    public function isFullyReleased(string $docType, int $docId): bool
    {
        $total = ReleaseStrategyApproval::where('document_type', $docType)
            ->where('document_id', $docId)
            ->count();

        if ($total === 0) {
            return false;
        }

        $approved = ReleaseStrategyApproval::where('document_type', $docType)
            ->where('document_id', $docId)
            ->where('status', ReleaseStrategyApproval::STATUS_APPROVED)
            ->count();

        return $total === $approved;
    }

    /**
     * Return all approval records with level info for a document.
     *
     * @return ReleaseStrategyApproval[]
     */
    public function getApprovalStatus(string $docType, int $docId): array
    {
        return ReleaseStrategyApproval::with(['level', 'approver', 'strategy'])
            ->where('document_type', $docType)
            ->where('document_id', $docId)
            ->join('release_strategy_levels', 'release_strategy_approvals.level_id', '=', 'release_strategy_levels.id')
            ->orderBy('release_strategy_levels.level', 'asc')
            ->select('release_strategy_approvals.*')
            ->get()
            ->all();
    }

    // -------------------------------------------------------------------------
    // CRUD helpers
    // -------------------------------------------------------------------------

    public function list(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ReleaseStrategy::with('levels')
            ->when(
                isset($filters['document_type']),
                fn ($q) => $q->forDocument($filters['document_type'])
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->latest();

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data): ReleaseStrategy
    {
        return ReleaseStrategy::create($data);
    }

    public function update(ReleaseStrategy $strategy, array $data): ReleaseStrategy
    {
        $strategy->update($data);
        return $strategy->fresh('levels');
    }

    public function delete(ReleaseStrategy $strategy): void
    {
        $strategy->delete();
    }

    public function addLevel(ReleaseStrategy $strategy, array $data): ReleaseStrategyLevel
    {
        return $strategy->levels()->create($data);
    }

    public function removeLevel(ReleaseStrategyLevel $level): void
    {
        $level->delete();
    }
}
