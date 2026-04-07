<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CoReposting;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\CostElement;
use App\Models\Accounting\InternalOrder;
use App\Models\Accounting\ProfitCenter;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CoRepostingService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
        private readonly InternalOrderService $internalOrderService
    ) {}

    // ----------------------------------------------------------------
    // List
    // ----------------------------------------------------------------

    public function list(array $filters): LengthAwarePaginator
    {
        $query = CoReposting::with([
            'costElement:id,code,name',
            'postedBy:id,name',
        ])->orderByDesc('posting_date');

        if (!empty($filters['from_date'])) {
            $query->whereDate('posting_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('posting_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['period'])) {
            $query->where('period', (int) $filters['period']);
        }

        if (!empty($filters['fiscal_year'])) {
            $query->where('fiscal_year', (int) $filters['fiscal_year']);
        }

        if (!empty($filters['from_type'])) {
            $query->where('from_type', $filters['from_type']);
        }

        if (!empty($filters['from_id'])) {
            $query->where('from_id', (int) $filters['from_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->paginate($perPage);
    }

    // ----------------------------------------------------------------
    // Create
    // ----------------------------------------------------------------

    public function create(array $data): CoReposting
    {
        return DB::transaction(function () use ($data): CoReposting {
            $orgId = (int) $data['organization_id'];

            // Validate sender
            $this->validateObject($orgId, $data['from_type'], (int) $data['from_id'], 'sender');

            // Validate receiver
            $this->validateObject($orgId, $data['to_type'], (int) $data['to_id'], 'receiver');

            // Validate cost element
            CostElement::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->findOrFail((int) $data['cost_element_id']);

            $amount = (float) $data['amount'];

            if ($amount <= 0) {
                throw new InvalidArgumentException('Reposting amount must be greater than zero.');
            }

            $repostingNumber = $this->numberGenerator->generate('REPO', null, $orgId);

            $reposting = CoReposting::create([
                'organization_id'  => $orgId,
                'reposting_number' => $repostingNumber,
                'posting_date'     => $data['posting_date'],
                'document_date'    => $data['document_date'] ?? $data['posting_date'],
                'period'           => (int) $data['period'],
                'fiscal_year'      => (int) $data['fiscal_year'],
                'from_type'        => $data['from_type'],
                'from_id'          => (int) $data['from_id'],
                'to_type'          => $data['to_type'],
                'to_id'            => (int) $data['to_id'],
                'cost_element_id'  => (int) $data['cost_element_id'],
                'amount'           => $amount,
                'currency_code'    => $data['currency_code'] ?? 'SAR',
                'narration'        => $data['narration'] ?? null,
                'status'           => CoReposting::STATUS_POSTED,
                'posted_by'        => $data['posted_by'] ?? auth()->id(),
            ]);

            // Update sender: subtract amount from actual
            $this->adjustActualAmount($data['from_type'], (int) $data['from_id'], -$amount);

            // Update receiver: add amount to actual
            $this->adjustActualAmount($data['to_type'], (int) $data['to_id'], $amount);

            return $reposting->fresh(['costElement:id,code,name', 'postedBy:id,name']);
        });
    }

    // ----------------------------------------------------------------
    // Reverse
    // ----------------------------------------------------------------

    public function reverse(CoReposting $reposting): CoReposting
    {
        return DB::transaction(function () use ($reposting): CoReposting {
            if ($reposting->isReversed()) {
                throw new InvalidArgumentException(
                    "Reposting [{$reposting->reposting_number}] is already reversed."
                );
            }

            $orgId         = (int) $reposting->organization_id;
            $mirrorNumber  = $this->numberGenerator->generate('REPO-REV', null, $orgId);

            // Create mirror reposting (swap from/to, same amount)
            $mirror = CoReposting::create([
                'organization_id'  => $orgId,
                'reposting_number' => $mirrorNumber,
                'posting_date'     => now()->toDateString(),
                'document_date'    => now()->toDateString(),
                'period'           => $reposting->period,
                'fiscal_year'      => $reposting->fiscal_year,
                'from_type'        => $reposting->to_type,   // swapped
                'from_id'          => $reposting->to_id,
                'to_type'          => $reposting->from_type, // swapped
                'to_id'            => $reposting->from_id,
                'cost_element_id'  => $reposting->cost_element_id,
                'amount'           => $reposting->amount,
                'currency_code'    => $reposting->currency_code,
                'narration'        => "Reversal of [{$reposting->reposting_number}]",
                'status'           => CoReposting::STATUS_POSTED,
                'reversed_by_id'   => $reposting->id,
                'posted_by'        => auth()->id(),
            ]);

            // Undo original sender/receiver balance adjustments
            $amount = (float) $reposting->amount;
            $this->adjustActualAmount($reposting->from_type, (int) $reposting->from_id, $amount);
            $this->adjustActualAmount($reposting->to_type, (int) $reposting->to_id, -$amount);

            // Mark original as reversed
            $reposting->update([
                'status'         => CoReposting::STATUS_REVERSED,
                'reversed_by_id' => $mirror->id,
                'reversed_at'    => now(),
            ]);

            return $reposting->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Validate that the object exists and belongs to the organization.
     */
    private function validateObject(int $orgId, string $type, int $id, string $role): void
    {
        $exists = match ($type) {
            CoReposting::FROM_COST_CENTER    => CostCenter::withoutGlobalScopes()
                ->where('organization_id', $orgId)->where('id', $id)->exists(),
            CoReposting::FROM_INTERNAL_ORDER => InternalOrder::withoutGlobalScopes()
                ->where('organization_id', $orgId)->where('id', $id)->exists(),
            CoReposting::FROM_PROFIT_CENTER  => ProfitCenter::withoutGlobalScopes()
                ->where('organization_id', $orgId)->where('id', $id)->exists(),
            default => throw new InvalidArgumentException("Unknown type [{$type}] for {$role}."),
        };

        if (!$exists) {
            throw new InvalidArgumentException(
                "The {$role} {$type} with id={$id} was not found in this organization."
            );
        }
    }

    /**
     * Increment or decrement the actual_amount on the targeted CO object.
     *
     * When a positive delta is applied to an InternalOrder (i.e. costs are
     * being added), the budget availability is checked before the update so
     * that a \DomainException is thrown if the budget would be exceeded.
     */
    private function adjustActualAmount(string $type, int $id, float $delta): void
    {
        $model = match ($type) {
            CoReposting::FROM_COST_CENTER    => null,   // CC tracks actuals via journal lines; skip direct update
            CoReposting::FROM_INTERNAL_ORDER => InternalOrder::withoutGlobalScopes()->find($id),
            CoReposting::FROM_PROFIT_CENTER  => null,   // PC tracks via journal lines; skip direct update
            default => null,
        };

        if ($model !== null && isset($model->actual_amount)) {
            // Guard: only check budget when costs are being added (positive delta).
            if ($delta > 0 && $model instanceof InternalOrder) {
                $this->internalOrderService->checkBudgetAvailability($model, $delta);
            }

            $newActual = (float) $model->actual_amount + $delta;
            $model->update(['actual_amount' => $newActual]);
        }
    }
}
