<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\AssetTransaction;
use App\Models\Accounting\AssetTransfer;
use App\Models\Accounting\FixedAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inter-Company Fixed Asset Transfer Service (SAP ABUMN equivalent).
 *
 * Handles the full lifecycle of an asset moving between two organisations:
 *  1. Initiate transfer (capture values at transfer date)
 *  2. Execute — retire the asset on the sending side, create it on the receiving side,
 *     post GL journals on both sides
 *  3. Cancel pending transfer
 */
class AssetTransferService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    // =========================================================================
    // List
    // =========================================================================

    public function index(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = AssetTransfer::where(function ($q) use ($organizationId): void {
            $q->where('sending_organization_id', $organizationId)
              ->orWhere('receiving_organization_id', $organizationId);
        })
        ->with(['fixedAsset:id,uuid,asset_number,name', 'receivingAsset:id,uuid,asset_number,name'])
        ->orderByDesc('transfer_date')
        ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    // =========================================================================
    // Create (initiate)
    // =========================================================================

    /**
     * Initiate an asset transfer. Records current asset values — does not move the asset yet.
     */
    public function create(FixedAsset $asset, array $data, int $userId): AssetTransfer
    {
        if ($asset->status !== FixedAsset::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Only active assets can be transferred.');
        }

        if ((int) $data['receiving_organization_id'] === (int) $asset->organization_id) {
            throw new InvalidArgumentException('Sending and receiving organisations must be different.');
        }

        return DB::transaction(function () use ($asset, $data, $userId): AssetTransfer {
            $gross     = (float) $asset->acquisition_cost;
            $accum     = (float) $asset->accumulated_depreciation;
            $nbv       = (float) $asset->book_value;

            $transferPrice = isset($data['transfer_price']) ? (float) $data['transfer_price'] : null;
            $effectiveValue = match ($data['transfer_type'] ?? AssetTransfer::TYPE_BOOK_VALUE) {
                AssetTransfer::TYPE_NEGOTIATED_PRICE => $transferPrice ?? $nbv,
                AssetTransfer::TYPE_GROSS_VALUE      => $gross,
                default                              => $nbv,
            };

            $gainLoss = $effectiveValue - $nbv;

            return AssetTransfer::create([
                'transfer_number'           => $this->generateNumber($asset->organization_id),
                'sending_organization_id'   => $asset->organization_id,
                'fixed_asset_id'            => $asset->id,
                'receiving_organization_id' => $data['receiving_organization_id'],
                'transfer_date'             => $data['transfer_date'],
                'transfer_type'             => $data['transfer_type'] ?? AssetTransfer::TYPE_BOOK_VALUE,
                'gross_value'               => $gross,
                'accumulated_depreciation'  => $accum,
                'net_book_value'            => $nbv,
                'transfer_price'            => $transferPrice,
                'gain_loss_amount'          => $gainLoss,
                'status'                    => AssetTransfer::STATUS_PENDING,
                'notes'                     => $data['notes'] ?? null,
                'created_by'                => $userId,
            ]);
        });
    }

    // =========================================================================
    // Execute
    // =========================================================================

    /**
     * Execute a pending transfer:
     *  - Retire (dispose) the asset on the sending side
     *  - Create a mirror asset on the receiving side
     *  - Post GL journals on both sides
     */
    public function execute(AssetTransfer $transfer, array $journalMeta): AssetTransfer
    {
        if (!$transfer->isPending()) {
            throw new InvalidArgumentException('Only pending transfers can be executed.');
        }

        return DB::transaction(function () use ($transfer, $journalMeta): AssetTransfer {
            $sendingAsset = $transfer->fixedAsset;
            $effectiveValue = $transfer->effectiveTransferValue();

            // ---- 1. Retire the asset on the sending side ----
            $sendingJe = $this->postSendingJournal($transfer, $sendingAsset, $effectiveValue, $journalMeta);

            $sendingAsset->update([
                'status'           => FixedAsset::STATUS_DISPOSED,
                'disposal_date'    => $transfer->transfer_date,
                'disposal_amount'  => $effectiveValue,
            ]);

            if ($sendingAsset->id) {
                AssetTransaction::create([
                    'organization_id' => $sendingAsset->organization_id,
                    'fixed_asset_id'  => $sendingAsset->id,
                    'transaction_type' => 'transfer',
                    'transaction_date' => $transfer->transfer_date,
                    'amount'           => $effectiveValue,
                    'description'      => "Inter-company transfer to org #{$transfer->receiving_organization_id}",
                    'journal_entry_id' => $sendingJe?->id,
                ]);
            }

            // ---- 2. Create a new asset on the receiving side ----
            $receivingAsset = $this->createReceivingAsset($transfer, $sendingAsset, $effectiveValue);

            // ---- 3. Post receiving journal ----
            $receivingJe = $this->postReceivingJournal($transfer, $receivingAsset, $effectiveValue, $journalMeta);

            $transfer->update([
                'status'               => AssetTransfer::STATUS_COMPLETED,
                'receiving_asset_id'   => $receivingAsset->id,
                'sending_journal_id'   => $sendingJe?->id,
                'receiving_journal_id' => $receivingJe?->id,
            ]);

            return $transfer->fresh(['fixedAsset', 'receivingAsset']);
        });
    }

    // =========================================================================
    // Cancel
    // =========================================================================

    public function cancel(AssetTransfer $transfer, string $reason): AssetTransfer
    {
        if (!$transfer->isPending()) {
            throw new InvalidArgumentException('Only pending transfers can be cancelled.');
        }

        $transfer->update([
            'status'               => AssetTransfer::STATUS_CANCELLED,
            'cancellation_reason'  => $reason,
        ]);

        return $transfer->fresh();
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function postSendingJournal(
        AssetTransfer $transfer,
        FixedAsset $asset,
        float $effectiveValue,
        array $meta
    ): ?\App\Models\Accounting\JournalEntry {
        $category = $asset->category ?? null;
        if (!$category || !$category->gl_asset_account_id) {
            return null; // No GL accounts configured — skip journal
        }

        $lines = [
            [
                'account_id'  => $category->gl_asset_account_id,
                'description' => 'Asset cost — inter-company disposal',
                'debit'       => 0,
                'credit'      => (float) $asset->acquisition_cost,
                'line_order'  => 1,
            ],
        ];

        if ($asset->accumulated_depreciation > 0 && $category->gl_accumulated_account_id) {
            $lines[] = [
                'account_id'  => $category->gl_accumulated_account_id,
                'description' => 'Clear accumulated depreciation',
                'debit'       => (float) $asset->accumulated_depreciation,
                'credit'      => 0,
                'line_order'  => 2,
            ];
        }

        // Gain/loss on transfer
        $gainLoss = (float) $transfer->gain_loss_amount;
        if (abs($gainLoss) > 0.00005) {
            $gainAccount = $gainLoss > 0
                ? $this->fallbackAccount($asset->organization_id, 'other_income')
                : $this->fallbackAccount($asset->organization_id, 'other_expense');

            if ($gainAccount) {
                $lines[] = [
                    'account_id'  => $gainAccount->id,
                    'description' => $gainLoss > 0 ? 'Gain on asset transfer' : 'Loss on asset transfer',
                    'debit'       => $gainLoss < 0 ? abs($gainLoss) : 0,
                    'credit'      => $gainLoss > 0 ? $gainLoss : 0,
                    'line_order'  => count($lines) + 1,
                ];
            }
        }

        // Receivable from receiving org
        $intercompanyAccount = $this->fallbackAccount($asset->organization_id, 'receivable');
        if ($intercompanyAccount) {
            $lines[] = [
                'account_id'  => $intercompanyAccount->id,
                'description' => "IC receivable — transfer #{$transfer->transfer_number}",
                'debit'       => $effectiveValue,
                'credit'      => 0,
                'line_order'  => count($lines) + 1,
            ];
        }

        $je = $this->journalService->createEntry(
            array_merge($meta, [
                'organization_id' => $asset->organization_id,
                'description'     => 'IC asset transfer (sending): ' . ($asset->asset_number ?? $asset->id),
                'reference'       => 'ASSET-XFER-S-' . $transfer->id,
            ]),
            $lines
        );
        $this->journalService->postEntry($je);

        return $je;
    }

    private function createReceivingAsset(AssetTransfer $transfer, FixedAsset $sendingAsset, float $value): FixedAsset
    {
        return FixedAsset::create([
            'organization_id'        => $transfer->receiving_organization_id,
            'asset_category_id'      => $sendingAsset->asset_category_id,
            'asset_number'           => $this->generateAssetNumber($transfer->receiving_organization_id),
            'name'             => $sendingAsset->name,
            'description'            => $sendingAsset->description
                . ' (Transferred from org #' . $transfer->sending_organization_id . ')',
            'acquisition_date'       => $transfer->transfer_date,
            'acquisition_cost'       => $value,
            'salvage_value'          => $sendingAsset->salvage_value,
            'useful_life_years'      => $sendingAsset->useful_life_years,
            'depreciation_method'    => $sendingAsset->depreciation_method,
            'accumulated_depreciation' => 0,
            'book_value'             => $value,
            'status'                 => FixedAsset::STATUS_ACTIVE,
        ]);
    }

    private function postReceivingJournal(
        AssetTransfer $transfer,
        FixedAsset $receivingAsset,
        float $value,
        array $meta
    ): ?\App\Models\Accounting\JournalEntry {
        $category = $receivingAsset->category ?? null;
        if (!$category || !$category->gl_asset_account_id) {
            return null;
        }

        $icPayable = $this->fallbackAccount($transfer->receiving_organization_id, 'payable');

        $lines = [
            [
                'account_id'  => $category->gl_asset_account_id,
                'description' => 'New asset — inter-company acquisition',
                'debit'       => $value,
                'credit'      => 0,
                'line_order'  => 1,
            ],
            [
                'account_id'  => $icPayable?->id ?? $category->gl_asset_account_id,
                'description' => "IC payable — transfer #{$transfer->transfer_number}",
                'debit'       => 0,
                'credit'      => $value,
                'line_order'  => 2,
            ],
        ];

        $je = $this->journalService->createEntry(
            array_merge($meta, [
                'organization_id' => $transfer->receiving_organization_id,
                'description'     => "IC asset transfer (receiving): {$receivingAsset->asset_number}",
                'reference'       => 'ASSET-XFER-R-' . $transfer->id,
            ]),
            $lines
        );
        $this->journalService->postEntry($je);

        return $je;
    }

    private function fallbackAccount(int $organizationId, string $subType): ?\App\Models\Accounting\Account
    {
        return \App\Models\Accounting\Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sub_type', $subType)
            ->where('is_active', true)
            ->where('is_header', false)
            ->orderBy('id')
            ->first();
    }

    private function generateNumber(int $organizationId): string
    {
        $count = AssetTransfer::where('sending_organization_id', $organizationId)->count() + 1;
        return 'AXFER-' . now()->format('Y') . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    private function generateAssetNumber(int $organizationId): string
    {
        $count = FixedAsset::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count() + 1;
        return 'FA-' . now()->format('Y') . '-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
