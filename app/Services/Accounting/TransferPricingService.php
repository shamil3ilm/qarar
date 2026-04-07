<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\TransferPrice;
use App\Models\Accounting\TransferPriceCondition;
use App\Models\Accounting\TransferPriceHistory;
use App\Models\Accounting\TransferPriceVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TransferPricingService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Find the most specific active transfer price for a given product and
     * profit-center pair on a given date.
     */
    public function getTransferPrice(
        int $productId,
        int $fromPCId,
        int $toPCId,
        string $date
    ): ?TransferPrice {
        return TransferPrice::active()
            ->effectiveOn($date)
            ->where('product_id', $productId)
            ->where('from_profit_center_id', $fromPCId)
            ->where('to_profit_center_id', $toPCId)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Calculate the transfer amount for a given quantity.
     *
     * Returns an array with:
     *  - base        : base price × quantity
     *  - conditions  : list of applied conditions with their amounts
     *  - total       : final total after all conditions
     */
    public function calculateTransferAmount(TransferPrice $tp, float $quantity): array
    {
        $baseTotal   = (float) $tp->base_price * $quantity;
        $conditionDetails = [];
        $conditionsTotal  = 0.0;

        $conditions = $tp->conditions()->get();

        foreach ($conditions as $condition) {
            $resolved = $condition->resolveAmount($baseTotal);

            // Discounts reduce the total; everything else adds
            $signed = $condition->condition_type === TransferPriceCondition::TYPE_DISCOUNT
                ? -$resolved
                : $resolved;

            $conditionDetails[] = [
                'condition_type' => $condition->condition_type,
                'is_percentage'  => $condition->is_percentage,
                'rate'           => (float) $condition->amount,
                'amount'         => $signed,
            ];

            $conditionsTotal += $signed;
        }

        return [
            'base'       => $baseTotal,
            'conditions' => $conditionDetails,
            'total'      => $baseTotal + $conditionsTotal,
        ];
    }

    /**
     * Create a new transfer price version.
     */
    public function createVersion(array $data): TransferPriceVersion
    {
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status']     = TransferPriceVersion::STATUS_DRAFT;

        return TransferPriceVersion::create($data);
    }

    /**
     * Activate a draft version. Any previously active version for the same
     * org + fiscal year is frozen first.
     */
    public function activateVersion(TransferPriceVersion $version): void
    {
        if (! $version->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft versions can be activated. Current status: ' . $version->status
            );
        }

        DB::transaction(function () use ($version): void {
            // Freeze the currently active version for the same fiscal year, if any
            TransferPriceVersion::where('organization_id', $version->organization_id)
                ->where('fiscal_year', $version->fiscal_year)
                ->where('status', TransferPriceVersion::STATUS_ACTIVE)
                ->where('id', '!=', $version->id)
                ->update(['status' => TransferPriceVersion::STATUS_FROZEN]);

            $version->update([
                'status'       => TransferPriceVersion::STATUS_ACTIVE,
                'activated_at' => now(),
            ]);
        });
    }

    /**
     * Record an intercompany transfer. Creates a journal entry debiting the
     * receiving entity and crediting the sending entity.
     */
    public function recordTransfer(array $data): void
    {
        $required = ['organization_id', 'transfer_price_id', 'quantity', 'debit_account_id', 'credit_account_id', 'entry_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $tp = TransferPrice::findOrFail($data['transfer_price_id']);
        $calc = $this->calculateTransferAmount($tp, (float) $data['quantity']);

        $amount = $calc['total'];

        $this->journalService->createEntry(
            [
                'organization_id' => $data['organization_id'],
                'entry_date'      => $data['entry_date'],
                'description'     => $data['description'] ?? 'Intercompany transfer pricing entry',
                'reference_type'  => 'transfer_price',
                'reference_id'    => $tp->id,
            ],
            [
                [
                    'account_id'  => $data['debit_account_id'],
                    'debit'       => $amount,
                    'credit'      => 0,
                    'description' => 'Transfer price debit',
                ],
                [
                    'account_id'  => $data['credit_account_id'],
                    'debit'       => 0,
                    'credit'      => $amount,
                    'description' => 'Transfer price credit',
                ],
            ]
        );
    }

    /**
     * Retrieve the full change history for a transfer price record.
     */
    public function getTransferPriceHistory(int $transferPriceId): Collection
    {
        return TransferPriceHistory::where('transfer_price_id', $transferPriceId)
            ->with('changedBy:id,name')
            ->orderByDesc('changed_at')
            ->get();
    }

    /**
     * Create a new transfer price, recording its initial history entry.
     */
    public function create(array $data): TransferPrice
    {
        return DB::transaction(function () use ($data): TransferPrice {
            $tp = TransferPrice::create($data);

            TransferPriceHistory::create([
                'transfer_price_id' => $tp->id,
                'changed_by'        => Auth::id(),
                'old_price'         => 0,
                'new_price'         => (float) $tp->base_price,
                'change_reason'     => 'Initial creation',
                'changed_at'        => now(),
            ]);

            return $tp;
        });
    }

    /**
     * Update a transfer price, recording a history entry when the price changes.
     */
    public function update(TransferPrice $tp, array $data): TransferPrice
    {
        return DB::transaction(function () use ($tp, $data): TransferPrice {
            $oldPrice = (float) $tp->base_price;

            $tp->update($data);
            $tp->refresh();

            $newPrice = (float) $tp->base_price;

            if ($oldPrice !== $newPrice) {
                TransferPriceHistory::create([
                    'transfer_price_id' => $tp->id,
                    'changed_by'        => Auth::id(),
                    'old_price'         => $oldPrice,
                    'new_price'         => $newPrice,
                    'change_reason'     => $data['change_reason'] ?? null,
                    'changed_at'        => now(),
                ]);
            }

            return $tp;
        });
    }
}
