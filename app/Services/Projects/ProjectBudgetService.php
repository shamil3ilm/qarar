<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\ProjectBudgetAvailabilityLog;
use App\Models\Projects\ProjectBudgetLineItem;
use App\Models\Projects\ProjectBudgetSupplement;
use App\Models\Projects\ProjectBudgetVersion;
use App\Models\Projects\WbsElement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectBudgetService
{
    // ── Version management ────────────────────────────────────────────────────

    public function listVersions(int $projectId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProjectBudgetVersion::forProject($projectId)
            ->with(['approvedBy'])
            ->withCount(['lineItems', 'supplements']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['fiscal_year'])) {
            $query->where('fiscal_year', (int) $filters['fiscal_year']);
        }

        if (isset($filters['is_current'])) {
            $query->where('is_current', (bool) $filters['is_current']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function createVersion(array $data): ProjectBudgetVersion
    {
        return ProjectBudgetVersion::create([
            'organization_id' => auth()->user()->organization_id,
            'project_id'      => $data['project_id'],
            'version_code'    => $data['version_code'],
            'version_name'    => $data['version_name'],
            'fiscal_year'     => (int) $data['fiscal_year'],
            'status'          => $data['status'] ?? ProjectBudgetVersion::STATUS_DRAFT,
            'is_current'      => $data['is_current'] ?? false,
            'total_budget'    => $data['total_budget'] ?? 0,
            'approved_by'     => $data['approved_by'] ?? null,
            'approved_at'     => $data['approved_at'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);
    }

    public function updateVersion(ProjectBudgetVersion $version, array $data): ProjectBudgetVersion
    {
        $version->update(array_filter([
            'version_code' => $data['version_code'] ?? null,
            'version_name' => $data['version_name'] ?? null,
            'fiscal_year'  => isset($data['fiscal_year']) ? (int) $data['fiscal_year'] : null,
            'notes'        => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return $version->fresh();
    }

    /**
     * Activate a budget version. Wraps in a transaction to safely
     * demote any existing current version and promote this one.
     */
    public function activateVersion(ProjectBudgetVersion $version): ProjectBudgetVersion
    {
        DB::transaction(function () use ($version): void {
            // Demote any currently-active current version for the project
            ProjectBudgetVersion::withoutGlobalScope('organization')
                ->where('project_id', $version->project_id)
                ->where('id', '!=', $version->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $version->status     = ProjectBudgetVersion::STATUS_ACTIVE;
            $version->is_current = true;
            $version->save();
        });

        return $version->fresh();
    }

    // ── Line items ────────────────────────────────────────────────────────────

    /**
     * Upsert a full set of line items for a version.
     * Lines are matched by wbs_element_id + cost_element_id.
     * Any line not present in $lines is deleted.
     */
    public function setLineItems(ProjectBudgetVersion $version, array $lines): void
    {
        DB::transaction(function () use ($version, $lines): void {
            $orgId = auth()->user()->organization_id;

            // Collect composite keys that arrive in the payload
            $incomingKeys = [];

            foreach ($lines as $line) {
                $wbsId  = $line['wbs_element_id'] ?? null;
                $ceId   = $line['cost_element_id'] ?? null;

                /** @var ProjectBudgetLineItem $item */
                $item = ProjectBudgetLineItem::withoutGlobalScope('organization')
                    ->where('project_budget_version_id', $version->id)
                    ->where('wbs_element_id', $wbsId)
                    ->where('cost_element_id', $ceId)
                    ->first();

                if ($item === null) {
                    $item = new ProjectBudgetLineItem;
                    $item->organization_id             = $orgId;
                    $item->project_budget_version_id   = $version->id;
                    $item->wbs_element_id              = $wbsId;
                    $item->cost_element_id             = $ceId;
                    $item->committed_amount            = 0;
                    $item->actual_amount               = 0;
                }

                $item->budgeted_amount   = (float) ($line['budgeted_amount'] ?? 0);
                $item->avac_action       = $line['avac_action'] ?? 'warning';
                $item->tolerance_percent = (float) ($line['tolerance_percent'] ?? 0);
                $item->save();

                $item->refreshAvailableAmount();

                $incomingKeys[] = $item->id;
            }

            // Remove stale line items not present in this payload
            ProjectBudgetLineItem::withoutGlobalScope('organization')
                ->where('project_budget_version_id', $version->id)
                ->whereNotIn('id', $incomingKeys)
                ->delete();

            $version->recalculateTotalBudget();
        });
    }

    public function updateLineItem(ProjectBudgetLineItem $item, array $data): ProjectBudgetLineItem
    {
        $item->update(array_intersect_key($data, array_flip([
            'budgeted_amount',
            'avac_action',
            'tolerance_percent',
        ])));

        $item->refreshAvailableAmount();

        return $item->fresh();
    }

    // ── Supplements ───────────────────────────────────────────────────────────

    public function createSupplement(array $data): ProjectBudgetSupplement
    {
        return ProjectBudgetSupplement::create([
            'organization_id'           => auth()->user()->organization_id,
            'project_budget_version_id' => $data['project_budget_version_id'],
            'wbs_element_id'            => $data['wbs_element_id'] ?? null,
            'supplement_type'           => $data['supplement_type'] ?? ProjectBudgetSupplement::TYPE_SUPPLEMENT,
            'amount'                    => $data['amount'],
            'reason'                    => $data['reason'] ?? null,
            'reference_number'          => $data['reference_number'] ?? null,
            'status'                    => ProjectBudgetSupplement::STATUS_PENDING,
        ]);
    }

    public function approveSupplement(ProjectBudgetSupplement $supplement, int $approvedBy): ProjectBudgetSupplement
    {
        DB::transaction(function () use ($supplement, $approvedBy): void {
            $supplement->status      = ProjectBudgetSupplement::STATUS_APPROVED;
            $supplement->approved_by = $approvedBy;
            $supplement->approved_at = now();
            $supplement->save();

            // Adjust the matching line item's budgeted amount
            $wbsId = $supplement->wbs_element_id;
            $versionId = $supplement->project_budget_version_id;
            $orgId = $supplement->organization_id;

            $item = ProjectBudgetLineItem::withoutGlobalScope('organization')
                ->where('project_budget_version_id', $versionId)
                ->where('wbs_element_id', $wbsId)
                ->whereNull('cost_element_id')
                ->first();

            if ($item === null) {
                $item = new ProjectBudgetLineItem;
                $item->organization_id           = $orgId;
                $item->project_budget_version_id = $versionId;
                $item->wbs_element_id            = $wbsId;
                $item->cost_element_id           = null;
                $item->committed_amount          = 0;
                $item->actual_amount             = 0;
                $item->budgeted_amount           = 0;
                $item->avac_action               = 'warning';
                $item->tolerance_percent         = 0;
                $item->save();
            }

            $item->budgeted_amount = (float) $item->budgeted_amount + (float) $supplement->amount;
            $item->save();
            $item->refreshAvailableAmount();

            $supplement->version->recalculateTotalBudget();
        });

        return $supplement->fresh();
    }

    public function rejectSupplement(ProjectBudgetSupplement $supplement): ProjectBudgetSupplement
    {
        $supplement->status = ProjectBudgetSupplement::STATUS_REJECTED;
        $supplement->save();

        return $supplement->fresh();
    }

    // ── Availability control ──────────────────────────────────────────────────

    /**
     * Check whether a given amount can be posted against a WBS element.
     *
     * Returns an array:
     *   ['result' => 'approved|warning|rejected', 'available' => float, 'message' => string]
     */
    public function checkAvailability(
        int    $wbsElementId,
        float  $amount,
        string $documentType,
        int    $documentId,
        ?int   $costElementId = null
    ): array {
        // 1. Resolve the project for this WBS element
        $wbs = WbsElement::withoutGlobalScope('organization')->findOrFail($wbsElementId);

        // 2. Find the active current budget version for the project
        $version = ProjectBudgetVersion::withoutGlobalScope('organization')
            ->where('project_id', $wbs->project_id)
            ->where('status', ProjectBudgetVersion::STATUS_ACTIVE)
            ->where('is_current', true)
            ->first();

        $orgId = auth()->user()?->organization_id ?? $wbs->organization_id;

        if ($version === null) {
            // No active budget — treat as no control (approved)
            $result = 'approved';
            $available = 0.0;
            $message = 'No active budget version found; posting allowed without control.';

            ProjectBudgetAvailabilityLog::create([
                'organization_id'             => $orgId,
                'project_budget_line_item_id' => 0,
                'wbs_element_id'              => $wbsElementId,
                'document_type'              => $documentType,
                'document_id'               => $documentId,
                'requested_amount'           => $amount,
                'available_amount'           => $available,
                'result'                    => $result,
                'message'                   => $message,
                'checked_at'                => now(),
                'checked_by'                => auth()->id(),
            ]);

            return compact('result', 'available', 'message');
        }

        // 3. Find or create the line item
        $item = ProjectBudgetLineItem::withoutGlobalScope('organization')
            ->where('project_budget_version_id', $version->id)
            ->where('wbs_element_id', $wbsElementId)
            ->where('cost_element_id', $costElementId)
            ->first();

        if ($item === null) {
            $item = new ProjectBudgetLineItem;
            $item->organization_id           = $orgId;
            $item->project_budget_version_id = $version->id;
            $item->wbs_element_id            = $wbsElementId;
            $item->cost_element_id           = $costElementId;
            $item->budgeted_amount           = 0;
            $item->committed_amount          = 0;
            $item->actual_amount             = 0;
            $item->available_amount          = 0;
            $item->avac_action               = 'warning';
            $item->tolerance_percent         = 0;
            $item->save();
        }

        $available = (float) $item->available_amount;
        $avacAction = $item->avac_action;
        $wouldBeOver = $item->isOverBudget()
            || ((float) $item->committed_amount + (float) $item->actual_amount + $amount)
               > (float) $item->budgeted_amount * (1 + (float) $item->tolerance_percent / 100);

        // 4. Determine result based on avac_action
        if ($avacAction === 'none' || ! $wouldBeOver) {
            $result  = 'approved';
            $message = $wouldBeOver
                ? sprintf('Amount approved (tolerance: %.2f%%).', (float) $item->tolerance_percent)
                : 'Sufficient budget available.';
        } elseif ($avacAction === 'warning') {
            $result  = 'warning';
            $message = sprintf(
                'Budget will be exceeded by %.4f. Posting allowed with warning.',
                ($amount - $available)
            );
        } else {
            // 'error'
            $result  = 'rejected';
            $message = sprintf(
                'Insufficient budget. Available: %.4f, Requested: %.4f.',
                $available,
                $amount
            );
        }

        // 5. Persist the audit log entry
        ProjectBudgetAvailabilityLog::create([
            'organization_id'             => $orgId,
            'project_budget_line_item_id' => $item->id,
            'wbs_element_id'              => $wbsElementId,
            'document_type'              => $documentType,
            'document_id'               => $documentId,
            'requested_amount'           => $amount,
            'available_amount'           => $available,
            'result'                    => $result,
            'message'                   => $message,
            'checked_at'                => now(),
            'checked_by'                => auth()->id(),
        ]);

        return compact('result', 'available', 'message');
    }

    /**
     * Increase committed_amount for a WBS line (e.g. when a PO is created).
     */
    public function postCommitment(int $wbsElementId, float $amount, ?int $costElementId = null): void
    {
        $item = $this->resolveOrCreateLineItem($wbsElementId, $costElementId);
        $item->committed_amount = (float) $item->committed_amount + $amount;
        $item->save();
        $item->refreshAvailableAmount();
    }

    /**
     * Convert a commitment into actual cost (e.g. when PO becomes invoice).
     */
    public function releaseCommitment(int $wbsElementId, float $amount, ?int $costElementId = null): void
    {
        $item = $this->resolveOrCreateLineItem($wbsElementId, $costElementId);
        $item->committed_amount = max(0.0, (float) $item->committed_amount - $amount);
        $item->actual_amount    = (float) $item->actual_amount + $amount;
        $item->save();
        $item->refreshAvailableAmount();
    }

    /**
     * Post an actual cost directly (e.g. journal entry or cost entry).
     */
    public function postActual(int $wbsElementId, float $amount, ?int $costElementId = null): void
    {
        $item = $this->resolveOrCreateLineItem($wbsElementId, $costElementId);
        $item->actual_amount = (float) $item->actual_amount + $amount;
        $item->save();
        $item->refreshAvailableAmount();
    }

    /**
     * Return a summary of budget status for a project.
     */
    public function getBudgetStatus(int $projectId): array
    {
        $version = ProjectBudgetVersion::withoutGlobalScope('organization')
            ->where('project_id', $projectId)
            ->where('status', ProjectBudgetVersion::STATUS_ACTIVE)
            ->where('is_current', true)
            ->with(['lineItems.wbsElement'])
            ->first();

        if ($version === null) {
            return [
                'has_budget'       => false,
                'version'          => null,
                'total_budget'     => 0.0,
                'total_committed'  => 0.0,
                'total_actual'     => 0.0,
                'total_available'  => 0.0,
                'by_wbs'           => [],
            ];
        }

        $items = $version->lineItems;

        $totalBudget    = $items->sum(fn ($i) => (float) $i->budgeted_amount);
        $totalCommitted = $items->sum(fn ($i) => (float) $i->committed_amount);
        $totalActual    = $items->sum(fn ($i) => (float) $i->actual_amount);
        $totalAvailable = $items->sum(fn ($i) => (float) $i->available_amount);

        $byWbs = $items->groupBy('wbs_element_id')->map(function ($group) {
            $first = $group->first();

            return [
                'wbs_element_id'   => $first->wbs_element_id,
                'wbs_code'         => $first->wbsElement?->wbs_code,
                'wbs_name'         => $first->wbsElement?->name,
                'budgeted_amount'  => $group->sum(fn ($i) => (float) $i->budgeted_amount),
                'committed_amount' => $group->sum(fn ($i) => (float) $i->committed_amount),
                'actual_amount'    => $group->sum(fn ($i) => (float) $i->actual_amount),
                'available_amount' => $group->sum(fn ($i) => (float) $i->available_amount),
                'is_over_budget'   => $group->contains(fn ($i) => $i->isOverBudget()),
            ];
        })->values()->all();

        return [
            'has_budget'      => true,
            'version'         => [
                'id'           => $version->id,
                'version_code' => $version->version_code,
                'version_name' => $version->version_name,
                'fiscal_year'  => $version->fiscal_year,
                'status'       => $version->status,
            ],
            'total_budget'    => $totalBudget,
            'total_committed' => $totalCommitted,
            'total_actual'    => $totalActual,
            'total_available' => $totalAvailable,
            'by_wbs'          => $byWbs,
        ];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Find the current active line item for a WBS element, or create one
     * under the active current version for the element's project.
     */
    private function resolveOrCreateLineItem(int $wbsElementId, ?int $costElementId): ProjectBudgetLineItem
    {
        $wbs     = WbsElement::withoutGlobalScope('organization')->findOrFail($wbsElementId);
        $version = ProjectBudgetVersion::withoutGlobalScope('organization')
            ->where('project_id', $wbs->project_id)
            ->where('status', ProjectBudgetVersion::STATUS_ACTIVE)
            ->where('is_current', true)
            ->firstOrFail();

        $item = ProjectBudgetLineItem::withoutGlobalScope('organization')
            ->where('project_budget_version_id', $version->id)
            ->where('wbs_element_id', $wbsElementId)
            ->where('cost_element_id', $costElementId)
            ->first();

        if ($item === null) {
            $item = new ProjectBudgetLineItem;
            $item->organization_id           = $wbs->organization_id;
            $item->project_budget_version_id = $version->id;
            $item->wbs_element_id            = $wbsElementId;
            $item->cost_element_id           = $costElementId;
            $item->budgeted_amount           = 0;
            $item->committed_amount          = 0;
            $item->actual_amount             = 0;
            $item->available_amount          = 0;
            $item->avac_action               = 'warning';
            $item->tolerance_percent         = 0;
            $item->save();
        }

        return $item;
    }
}
