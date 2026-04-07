<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\RoutingHeader;
use App\Models\Manufacturing\RoutingOperation;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoutingService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Paginate routing headers with optional filters.
     *
     * @param array{product_id?: int, is_default?: bool, per_page?: int} $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        return RoutingHeader::with(['product', 'operations.workCenter'])
            ->when(isset($filters['product_id']), fn ($q) => $q->forProduct((int) $filters['product_id']))
            ->when(isset($filters['is_default']), fn ($q) => $q->where('is_default', (bool) $filters['is_default']))
            ->when(isset($filters['valid']), fn ($q) => $q->valid())
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a routing header together with its initial operations.
     *
     * @param array{
     *   product_id: int,
     *   routing_number?: string,
     *   alternative?: string,
     *   is_default?: bool,
     *   valid_from?: string,
     *   valid_to?: string,
     *   operations?: array<int, array{operation_code: string, description: string, work_center_id: int, sequence_number?: int, setup_time?: float, machine_time?: float, labor_time?: float, control_key?: string}>
     * } $data
     */
    public function store(array $data): RoutingHeader
    {
        return DB::transaction(function () use ($data): RoutingHeader {
            $orgId = auth()->user()->organization_id;

            $routingNumber = $data['routing_number']
                ?? $this->numberGenerator->generate('RH');

            $routing = RoutingHeader::create([
                'organization_id' => $orgId,
                'product_id'      => $data['product_id'],
                'routing_number'  => $routingNumber,
                'alternative'     => $data['alternative'] ?? '1',
                'is_default'      => $data['is_default'] ?? true,
                'valid_from'      => $data['valid_from'] ?? null,
                'valid_to'        => $data['valid_to'] ?? null,
            ]);

            foreach ($data['operations'] ?? [] as $index => $opData) {
                $this->createOperation($routing, $opData, $index);
            }

            return $routing->fresh(['product', 'operations.workCenter']);
        });
    }

    /**
     * Add a single operation to an existing routing header.
     *
     * @param array{
     *   operation_code: string,
     *   description: string,
     *   work_center_id: int,
     *   sequence_number?: int,
     *   setup_time?: float,
     *   machine_time?: float,
     *   labor_time?: float,
     *   control_key?: string
     * } $data
     */
    public function addOperation(RoutingHeader $routing, array $data): RoutingOperation
    {
        return DB::transaction(function () use ($routing, $data): RoutingOperation {
            $sequenceNumber = $data['sequence_number']
                ?? $this->nextSequenceNumber($routing);

            return RoutingOperation::create([
                'routing_id'      => $routing->id,
                'sequence_number' => $sequenceNumber,
                'operation_code'  => $data['operation_code'],
                'description'     => $data['description'],
                'work_center_id'  => $data['work_center_id'],
                'setup_time'      => $data['setup_time'] ?? 0,
                'machine_time'    => $data['machine_time'] ?? 0,
                'labor_time'      => $data['labor_time'] ?? 0,
                'control_key'     => $data['control_key'] ?? null,
            ]);
        });
    }

    /**
     * Calculate total production lead time in hours for a product/quantity pair.
     * Uses the default, currently-valid routing for the product.
     */
    public function calculateLeadTime(int $productId, float $quantity): float
    {
        $routing = RoutingHeader::with('operations')
            ->forProduct($productId)
            ->default()
            ->valid()
            ->first();

        if ($routing === null) {
            return 0.0;
        }

        return $routing->calculateLeadTime($quantity);
    }

    /**
     * Soft-delete a routing header (cascades to operations via DB).
     */
    public function destroy(RoutingHeader $routing): void
    {
        $routing->delete();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Create a single routing operation within an open transaction.
     */
    private function createOperation(RoutingHeader $routing, array $opData, int $fallbackIndex): RoutingOperation
    {
        $sequenceNumber = $opData['sequence_number']
            ?? (($fallbackIndex + 1) * 10); // 10, 20, 30 …

        return RoutingOperation::create([
            'routing_id'      => $routing->id,
            'sequence_number' => $sequenceNumber,
            'operation_code'  => $opData['operation_code'],
            'description'     => $opData['description'],
            'work_center_id'  => $opData['work_center_id'],
            'setup_time'      => $opData['setup_time'] ?? 0,
            'machine_time'    => $opData['machine_time'] ?? 0,
            'labor_time'      => $opData['labor_time'] ?? 0,
            'control_key'     => $opData['control_key'] ?? null,
        ]);
    }

    /**
     * Determine the next sequence number (last + 10, or 10 if no operations yet).
     */
    private function nextSequenceNumber(RoutingHeader $routing): int
    {
        $last = RoutingOperation::where('routing_id', $routing->id)
            ->max('sequence_number');

        return $last !== null ? (int) $last + 10 : 10;
    }
}
