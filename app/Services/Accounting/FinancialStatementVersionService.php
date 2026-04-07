<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\FinancialStatementVersion;
use App\Models\Accounting\FinancialStatementVersionNode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinancialStatementVersionService
{
    public function __construct(
        private AccountBalanceService $balanceService
    ) {}

    /**
     * List FSVs with optional filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = FinancialStatementVersion::with(['createdBy'])
            ->orderBy('name');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 20;

        return $query->paginate($perPage);
    }

    /**
     * Create a new FSV.
     */
    public function create(array $data): FinancialStatementVersion
    {
        $this->validateType($data['type'] ?? '');

        return DB::transaction(function () use ($data): FinancialStatementVersion {
            $data['created_by'] = $data['created_by'] ?? auth()->id();

            if (!empty($data['is_default']) && $data['is_default']) {
                FinancialStatementVersion::where('organization_id', auth()->user()->organization_id)
                    ->where('type', $data['type'])
                    ->update(['is_default' => false]);
            }

            return FinancialStatementVersion::create($data);
        });
    }

    /**
     * Update an FSV.
     */
    public function update(FinancialStatementVersion $fsv, array $data): FinancialStatementVersion
    {
        if (isset($data['type'])) {
            $this->validateType($data['type']);
        }

        return DB::transaction(function () use ($fsv, $data): FinancialStatementVersion {
            if (!empty($data['is_default']) && $data['is_default']) {
                $type = $data['type'] ?? $fsv->type;
                FinancialStatementVersion::where('organization_id', $fsv->organization_id)
                    ->where('type', $type)
                    ->where('id', '!=', $fsv->id)
                    ->update(['is_default' => false]);
            }

            $fsv->update($data);

            return $fsv->fresh();
        });
    }

    /**
     * Delete an FSV (soft-delete).
     */
    public function delete(FinancialStatementVersion $fsv): void
    {
        $fsv->delete();
    }

    /**
     * Add a node to an FSV.
     */
    public function addNode(FinancialStatementVersion $fsv, array $data): FinancialStatementVersionNode
    {
        $this->validateNodeType($data['node_type'] ?? '');

        if ($data['node_type'] === 'account' && empty($data['account_id'])) {
            throw new InvalidArgumentException("account_id is required for node_type 'account'.");
        }

        return FinancialStatementVersionNode::create(array_merge($data, [
            'fsv_id' => $fsv->id,
            'organization_id' => $fsv->organization_id,
        ]));
    }

    /**
     * Remove a node from an FSV.
     */
    public function removeNode(FinancialStatementVersionNode $node): void
    {
        DB::transaction(function () use ($node): void {
            // Re-parent children to grandparent
            FinancialStatementVersionNode::where('parent_id', $node->id)
                ->update(['parent_id' => $node->parent_id]);

            $node->delete();
        });
    }

    /**
     * Generate the FSV report for a given period.
     * Walks the node tree and aggregates account balances.
     */
    public function generate(
        FinancialStatementVersion $fsv,
        string $periodStart,
        string $periodEnd
    ): array {
        $rootNodes = FinancialStatementVersionNode::where('fsv_id', $fsv->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $tree = $rootNodes->map(
            fn($node) => $this->buildNode($node, $periodEnd)
        )->all();

        return [
            'fsv_id' => $fsv->id,
            'fsv_name' => $fsv->name,
            'type' => $fsv->type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'nodes' => $tree,
        ];
    }

    /**
     * Recursively build a node with its balance and children.
     */
    private function buildNode(FinancialStatementVersionNode $node, string $asOfDate): array
    {
        $children = $node->children()->get();

        if ($node->node_type === 'account' && $node->account_id !== null) {
            $balance = $this->getAccountBalance($node->account_id, $asOfDate);
            $amount = $balance * $node->sign;

            return [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'node_type' => $node->node_type,
                'label' => $node->label,
                'sort_order' => $node->sort_order,
                'account_id' => $node->account_id,
                'sign' => $node->sign,
                'amount' => round($amount, 4),
                'children' => [],
            ];
        }

        $childNodes = $children->map(
            fn($child) => $this->buildNode($child, $asOfDate)
        )->all();

        $total = array_reduce(
            $childNodes,
            fn(float $carry, array $item) => $carry + (float) ($item['amount'] ?? 0),
            0.0
        );

        return [
            'id' => $node->id,
            'uuid' => $node->uuid,
            'node_type' => $node->node_type,
            'label' => $node->label,
            'sort_order' => $node->sort_order,
            'account_id' => null,
            'sign' => $node->sign,
            'amount' => round($total * $node->sign, 4),
            'children' => $childNodes,
        ];
    }

    /**
     * Retrieve account closing balance as of a date.
     */
    private function getAccountBalance(int $accountId, string $asOfDate): float
    {
        try {
            $balance = $this->balanceService->getAccountBalance(
                $accountId,
                null,
                $asOfDate,
                false
            );

            return (float) ($balance['closing_balance'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function validateType(string $type): void
    {
        $valid = [
            FinancialStatementVersion::TYPE_BALANCE_SHEET,
            FinancialStatementVersion::TYPE_INCOME_STATEMENT,
            FinancialStatementVersion::TYPE_CASH_FLOW,
        ];

        if (!in_array($type, $valid, true)) {
            throw new InvalidArgumentException("Invalid FSV type '{$type}'.");
        }
    }

    private function validateNodeType(string $nodeType): void
    {
        if (!in_array($nodeType, ['header', 'account', 'total'], true)) {
            throw new InvalidArgumentException("Invalid node_type '{$nodeType}'.");
        }
    }
}
