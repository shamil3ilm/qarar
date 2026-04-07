<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MaterialValuationService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Update the moving average price (MAP) for a product after a receipt.
     *
     * Formula: new_MAP = (current_stock_value + received_value) / (current_qty + received_qty)
     *
     * @return array{old_price: float, new_price: float, stock_qty: float, stock_value: float}
     */
    public function updateMovingAveragePrice(
        int $productId,
        int $orgId,
        float $receivedQty,
        float $receivedValue
    ): array {
        return DB::transaction(function () use ($productId, $orgId, $receivedQty, $receivedValue) {
            $product = Product::where('id', $productId)
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->firstOrFail();

            // Aggregate current stock across all warehouses for this org
            $stockRows = StockLevel::where('product_id', $productId)
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->get();

            $currentQty   = (float) $stockRows->sum('quantity');
            $currentValue = (float) $stockRows->sum('total_value');

            $oldPrice = $currentQty > 0
                ? bcdiv((string) $currentValue, (string) $currentQty, 6)
                : (string) ($product->purchase_price ?? 0);
            $oldPrice = (float) $oldPrice;

            $newTotalQty   = bcadd((string) $currentQty, (string) $receivedQty, 6);
            $newTotalValue = bcadd((string) $currentValue, (string) $receivedValue, 6);

            if ((float) $newTotalQty <= 0) {
                return [
                    'old_price'   => $oldPrice,
                    'new_price'   => $oldPrice,
                    'stock_qty'   => 0.0,
                    'stock_value' => 0.0,
                ];
            }

            $newMap = (float) bcdiv($newTotalValue, $newTotalQty, 6);

            // Update average_cost and total_value on every stock-level row
            foreach ($stockRows as $sl) {
                $sl->average_cost = $newMap;
                $sl->total_value  = (float) bcmul((string) $sl->quantity, (string) $newMap, 4);
                $sl->save();
            }

            // Also persist as last_purchase_price on the product for reference
            $product->purchase_price = $newMap;
            $product->save();

            return [
                'old_price'   => $oldPrice,
                'new_price'   => $newMap,
                'stock_qty'   => (float) $newTotalQty,
                'stock_value' => (float) $newTotalValue,
            ];
        });
    }

    /**
     * Post a standard cost price variance journal entry.
     *
     * Dr: Purchase Price Variance account
     * Cr: Inventory account   (when actual > standard)
     * — reversed when actual < standard.
     */
    public function postStandardCostVariance(
        int $productId,
        float $actualCost,
        float $standardCost,
        float $quantity,
        int $orgId
    ): void {
        $product = Product::where('id', $productId)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        if ($product->costing_method !== Product::COSTING_STANDARD) {
            return;
        }

        $variance = (float) bcmul(
            bcsub((string) $actualCost, (string) $standardCost, 6),
            (string) $quantity,
            4
        );

        if (abs($variance) < 0.0001) {
            return;
        }

        $inventoryAccount = Account::where('organization_id', $orgId)
            ->where('account_type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%Inventory%')
                    ->orWhere('name', 'like', '%Stock%');
            })
            ->first();

        $varianceAccount = Account::where('organization_id', $orgId)
            ->where(function ($q) {
                $q->where('name', 'like', '%Purchase Price Variance%')
                    ->orWhere('name', 'like', '%PPV%')
                    ->orWhere('name', 'like', '%COGS Variance%');
            })
            ->first();

        if (!$inventoryAccount || !$varianceAccount) {
            Log::info('Standard cost variance journal skipped: accounts not configured', [
                'product_id' => $productId,
                'variance'   => $variance,
            ]);

            return;
        }

        // Positive variance: actual > standard → debit PPV, credit Inventory
        // Negative variance: actual < standard → debit Inventory, credit PPV
        $absVariance = abs($variance);

        $lines = $variance > 0
            ? [
                ['account_id' => $varianceAccount->id, 'description' => "PPV – product #{$productId}", 'debit' => $absVariance, 'credit' => 0],
                ['account_id' => $inventoryAccount->id, 'description' => "PPV offset – product #{$productId}", 'debit' => 0, 'credit' => $absVariance],
            ]
            : [
                ['account_id' => $inventoryAccount->id, 'description' => "PPV offset – product #{$productId}", 'debit' => $absVariance, 'credit' => 0],
                ['account_id' => $varianceAccount->id, 'description' => "PPV – product #{$productId}", 'debit' => 0, 'credit' => $absVariance],
            ];

        try {
            $this->journalService->createEntry([
                'organization_id' => $orgId,
                'entry_date'      => now()->toDateString(),
                'reference'       => "PPV-{$productId}-" . now()->format('Ymd'),
                'description'     => "Standard cost variance – product #{$productId}",
                'status'          => 'posted',
            ], $lines);
        } catch (\Throwable $e) {
            Log::error('Failed to post standard cost variance journal entry', [
                'product_id' => $productId,
                'variance'   => $variance,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate the total inventory value for an organisation, optionally
     * scoped to a single warehouse.
     *
     * @return array{total_value: float, currency: string, by_product: array<int, array{product_id: int, name: string, qty: float, unit_cost: float, total_value: float}>}
     */
    public function calculateInventoryValue(int $orgId, ?int $warehouseId = null): array
    {
        $query = StockLevel::with('product')
            ->where('organization_id', $orgId)
            ->where('quantity', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $rows = $query->get();

        $byProduct = [];
        $totalValue = 0.0;

        foreach ($rows as $sl) {
            $pid = $sl->product_id;

            if (!isset($byProduct[$pid])) {
                $byProduct[$pid] = [
                    'product_id'  => $pid,
                    'name'        => $sl->product?->name ?? "Product #{$pid}",
                    'qty'         => 0.0,
                    'unit_cost'   => (float) $sl->average_cost,
                    'total_value' => 0.0,
                ];
            }

            $byProduct[$pid]['qty']         += (float) $sl->quantity;
            $byProduct[$pid]['total_value']  += (float) $sl->total_value;
            $totalValue                      += (float) $sl->total_value;
        }

        // Recalculate blended unit_cost per product
        foreach ($byProduct as &$row) {
            $denominator = (string) $row['qty'];
            $row['unit_cost'] = $denominator !== '0' && $row['qty'] > 0
                ? (float) bcdiv((string) $row['total_value'], $denominator, 4)
                : 0.0;
        }
        unset($row);

        return [
            'total_value' => round($totalValue, 4),
            'currency'    => 'SAR', // TODO: pull from org settings
            'by_product'  => array_values($byProduct),
        ];
    }

    /**
     * Revalue inventory for a product by setting a new unit cost.
     *
     * Posts a revaluation journal entry and updates stock_levels.
     */
    public function revalueInventory(int $orgId, int $productId, float $newUnitCost): void
    {
        DB::transaction(function () use ($orgId, $productId, $newUnitCost) {
            if ($newUnitCost < 0) {
                throw new InvalidArgumentException('New unit cost cannot be negative.');
            }

            $product = Product::where('id', $productId)
                ->where('organization_id', $orgId)
                ->firstOrFail();

            $stockLevels = StockLevel::where('product_id', $productId)
                ->where('organization_id', $orgId)
                ->where('quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            if ($stockLevels->isEmpty()) {
                return;
            }

            $oldValue = (float) $stockLevels->sum('total_value');
            $totalQty = (float) $stockLevels->sum('quantity');
            $newValue = (float) bcmul((string) $totalQty, (string) $newUnitCost, 4);
            $diff     = (float) bcsub((string) $newValue, (string) $oldValue, 4);

            // Update each stock level row
            foreach ($stockLevels as $sl) {
                $sl->average_cost = $newUnitCost;
                $sl->total_value  = (float) bcmul((string) $sl->quantity, (string) $newUnitCost, 4);
                $sl->save();
            }

            $product->purchase_price = $newUnitCost;
            $product->save();

            if (abs($diff) < 0.0001) {
                return;
            }

            // Post revaluation journal entry
            $inventoryAccount = Account::where('organization_id', $orgId)
                ->where('account_type', 'asset')
                ->where(function ($q) {
                    $q->where('name', 'like', '%Inventory%')
                        ->orWhere('name', 'like', '%Stock%');
                })
                ->first();

            $revalAccount = Account::where('organization_id', $orgId)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Inventory Revaluation%')
                        ->orWhere('name', 'like', '%Revaluation Reserve%');
                })
                ->first();

            if (!$inventoryAccount || !$revalAccount) {
                Log::info('Inventory revaluation journal skipped: accounts not configured', [
                    'product_id' => $productId,
                    'diff'       => $diff,
                ]);

                return;
            }

            // Positive diff: inventory goes up → Dr Inventory, Cr Revaluation
            $lines = $diff > 0
                ? [
                    ['account_id' => $inventoryAccount->id, 'description' => "Inventory revaluation – product #{$productId}", 'debit' => abs($diff), 'credit' => 0],
                    ['account_id' => $revalAccount->id,     'description' => "Inventory revaluation – product #{$productId}", 'debit' => 0, 'credit' => abs($diff)],
                ]
                : [
                    ['account_id' => $revalAccount->id,     'description' => "Inventory revaluation – product #{$productId}", 'debit' => abs($diff), 'credit' => 0],
                    ['account_id' => $inventoryAccount->id, 'description' => "Inventory revaluation – product #{$productId}", 'debit' => 0, 'credit' => abs($diff)],
                ];

            try {
                $this->journalService->createEntry([
                    'organization_id' => $orgId,
                    'entry_date'      => now()->toDateString(),
                    'reference'       => "REVAL-{$productId}-" . now()->format('Ymd'),
                    'description'     => "Inventory revaluation – product #{$productId}",
                    'status'          => 'posted',
                ], $lines);
            } catch (\Throwable $e) {
                Log::error('Failed to post inventory revaluation journal entry', [
                    'product_id' => $productId,
                    'diff'       => $diff,
                    'error'      => $e->getMessage(),
                ]);
            }
        });
    }
}
