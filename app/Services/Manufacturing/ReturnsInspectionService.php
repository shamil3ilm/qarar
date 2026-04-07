<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ReturnsInspectionDefect;
use App\Models\Manufacturing\ReturnsInspectionLot;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ReturnsInspectionService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * List returns inspection lots with optional filters.
     */
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return ReturnsInspectionLot::with(['product', 'warehouse'])
            ->withCount('defects')
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['return_type']),
                fn ($q) => $q->where('return_type', $filters['return_type'])
            )
            ->when(
                isset($filters['product_id']),
                fn ($q) => $q->where('product_id', $filters['product_id'])
            )
            ->when(
                isset($filters['warehouse_id']),
                fn ($q) => $q->where('warehouse_id', $filters['warehouse_id'])
            )
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where('lot_number', 'like', '%' . $filters['search'] . '%')
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Create a new returns inspection lot.
     */
    public function create(array $data): ReturnsInspectionLot
    {
        $data['lot_number'] = $this->numberGenerator->generate('RIL');

        return ReturnsInspectionLot::create($data);
    }

    /**
     * Find a lot by integer ID or UUID string.
     */
    public function show(int|string $id): ReturnsInspectionLot
    {
        if (is_int($id) || ctype_digit((string) $id)) {
            $lot = ReturnsInspectionLot::with(['product', 'warehouse', 'defects', 'qualityPlan'])
                ->find((int) $id);
        } else {
            $lot = ReturnsInspectionLot::with(['product', 'warehouse', 'defects', 'qualityPlan'])
                ->where('uuid', $id)
                ->first();
        }

        if ($lot === null) {
            throw new InvalidArgumentException('Returns inspection lot not found.');
        }

        return $lot;
    }

    /**
     * Transition a lot from open → in_inspection.
     */
    public function startInspection(ReturnsInspectionLot $lot): ReturnsInspectionLot
    {
        if (! $lot->canStartInspection()) {
            throw new InvalidArgumentException(
                "Inspection can only be started when the lot is in 'open' status. "
                . "Current status: {$lot->status}."
            );
        }

        $lot->startInspection();

        return $lot->fresh();
    }

    /**
     * Add a defect record to an inspection lot.
     */
    public function addDefect(ReturnsInspectionLot $lot, array $data): ReturnsInspectionDefect
    {
        $data['returns_inspection_lot_id'] = $lot->id;
        $data['organization_id']           = $lot->organization_id;

        return ReturnsInspectionDefect::create($data);
    }

    /**
     * Update an existing defect record.
     */
    public function updateDefect(ReturnsInspectionDefect $defect, array $data): ReturnsInspectionDefect
    {
        $defect->update($data);

        return $defect->fresh();
    }

    /**
     * Remove a defect record.
     */
    public function removeDefect(ReturnsInspectionDefect $defect): void
    {
        $defect->delete();
    }

    /**
     * Record the usage decision for a lot.
     */
    public function makeUsageDecision(ReturnsInspectionLot $lot, array $data): ReturnsInspectionLot
    {
        if (! $lot->canMakeUsageDecision()) {
            throw new InvalidArgumentException(
                "Usage decision can only be made when the lot is 'in_inspection'. "
                . "Current status: {$lot->status}."
            );
        }

        $lot->makeUsageDecision(
            decision: $data['usage_decision'],
            accepted: (float) ($data['accepted_quantity'] ?? 0),
            rejected: (float) ($data['rejected_quantity'] ?? 0),
            rework:   (float) ($data['rework_quantity']   ?? 0),
            userId:   (int) ($data['user_id'] ?? auth()->id()),
            notes:    $data['notes'] ?? null,
        );

        return $lot->fresh();
    }

    /**
     * Post stock movements and close the lot.
     *
     * In a full implementation this would create StockMovement records for
     * the accepted (back to stock), rejected (to scrap/vendor), and rework
     * quantities.  For now we record the flag and transition to closed.
     */
    public function postStockMovements(ReturnsInspectionLot $lot): ReturnsInspectionLot
    {
        if (! $lot->canPostStock()) {
            throw new InvalidArgumentException(
                "Stock can only be posted after a usage decision has been made and has not yet been posted."
            );
        }

        $lot->update([
            'stock_posted'    => true,
            'stock_posted_at' => now(),
            'status'          => ReturnsInspectionLot::STATUS_CLOSED,
        ]);

        return $lot->fresh();
    }

    /**
     * Cancel an open inspection lot.
     */
    public function cancel(ReturnsInspectionLot $lot): ReturnsInspectionLot
    {
        if ($lot->status !== ReturnsInspectionLot::STATUS_OPEN) {
            throw new InvalidArgumentException(
                "Only lots in 'open' status can be cancelled. Current status: {$lot->status}."
            );
        }

        $lot->update(['status' => ReturnsInspectionLot::STATUS_CANCELLED]);

        return $lot->fresh();
    }
}
