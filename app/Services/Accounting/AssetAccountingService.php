<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\AssetTransaction;
use App\Models\Accounting\DepreciationRun;
use App\Models\Accounting\DepreciationRunLine;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FiscalYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssetAccountingService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Create a new fixed asset and record the acquisition transaction.
     */
    public function createAsset(array $data, int $userId): FixedAsset
    {
        return DB::transaction(function () use ($data, $userId): FixedAsset {
            $acquisitionCost = (float) $data['acquisition_cost'];

            $asset = FixedAsset::create([
                'organization_id' => $data['organization_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'asset_category_id' => $data['asset_category_id'],
                'asset_number' => $data['asset_number'] ?? $this->generateAssetNumber($data['organization_id']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'location' => $data['location'] ?? null,
                'status' => FixedAsset::STATUS_ACTIVE,
                'acquisition_date' => $data['acquisition_date'],
                'acquisition_cost' => $acquisitionCost,
                'salvage_value' => (float) ($data['salvage_value'] ?? 0),
                'useful_life_years' => (float) $data['useful_life_years'],
                'depreciation_method' => $data['depreciation_method'] ?? FixedAsset::DEPRECIATION_STRAIGHT_LINE,
                'accumulated_depreciation' => 0,
                'book_value' => $acquisitionCost,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            AssetTransaction::create([
                'organization_id' => $asset->organization_id,
                'fixed_asset_id' => $asset->id,
                'transaction_type' => AssetTransaction::TYPE_ACQUISITION,
                'transaction_date' => $asset->acquisition_date,
                'amount' => $acquisitionCost,
                'description' => "Asset acquisition: {$asset->name}",
                'created_by' => $userId,
            ]);

            return $asset->fresh(['category', 'createdBy']);
        });
    }

    /**
     * Update an existing fixed asset.
     */
    public function updateAsset(FixedAsset $asset, array $data): FixedAsset
    {
        return DB::transaction(function () use ($asset, $data): FixedAsset {
            $allowed = [
                'name', 'description', 'serial_number', 'location',
                'notes', 'branch_id', 'asset_category_id',
                'useful_life_years', 'depreciation_method', 'salvage_value',
            ];

            $updates = array_intersect_key($data, array_flip($allowed));
            $asset->update($updates);

            return $asset->fresh(['category']);
        });
    }

    /**
     * Create a depreciation run for all active depreciable assets in the given period.
     * The run is created in 'pending' status; call postDepreciationRun() to post it.
     */
    public function runDepreciation(array $data, int $userId): DepreciationRun
    {
        return DB::transaction(function () use ($data, $userId): DepreciationRun {
            $organizationId = $data['organization_id'];
            $periodStart = Carbon::parse($data['period_start']);
            $periodEnd = Carbon::parse($data['period_end']);
            $fiscalYearId = $data['fiscal_year_id'];

            // Fix 4: Prevent duplicate runs atomically. Lock the table row for this
            // org+period combination so concurrent requests cannot both pass the guard.
            // Also include STATUS_PENDING to catch runs that have been created but not yet
            // transitioned to PROCESSING or POSTED.
            $existingRun = DepreciationRun::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->whereIn('status', [
                    DepreciationRun::STATUS_PENDING,
                    DepreciationRun::STATUS_POSTED,
                    DepreciationRun::STATUS_PROCESSING,
                ])
                ->lockForUpdate()
                ->first();

            if ($existingRun !== null) {
                throw new InvalidArgumentException(
                    'A depreciation run already exists for this period (ID: ' . $existingRun->id . ').'
                );
            }

            $run = DepreciationRun::create([
                'organization_id' => $organizationId,
                'fiscal_year_id' => $fiscalYearId,
                'run_date' => now()->toDateString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => DepreciationRun::STATUS_PROCESSING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Calculate months in the period (at least 1)
            $months = max(1, (int) $periodStart->diffInMonths($periodEnd) + 1);

            $assets = FixedAsset::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->depreciable()
                ->get();

            $totalDepreciation = 0.0;
            $lineCount = 0;

            try {
                foreach ($assets as $asset) {
                    // Skip assets acquired after the period end
                    if ($asset->acquisition_date->gt($periodEnd)) {
                        continue;
                    }

                    // Skip assets already depreciated past this period
                    if ($asset->last_depreciation_date !== null && $asset->last_depreciation_date->gte($periodEnd)) {
                        continue;
                    }

                    $depreciationAmount = $asset->calculatePeriodicDepreciation($months);

                    if ($depreciationAmount <= 0) {
                        continue;
                    }

                    $openingBookValue = (float) $asset->book_value;
                    $tentativeClosing = bcsub((string)$openingBookValue, (string)$depreciationAmount, 4);
                    $closingBookValue = bccomp($tentativeClosing, (string)(float)$asset->salvage_value, 4) < 0
                        ? (string)(float)$asset->salvage_value
                        : $tentativeClosing;
                    $actualDepreciation = bcsub((string)$openingBookValue, (string)$closingBookValue, 4);

                    DepreciationRunLine::create([
                        'depreciation_run_id' => $run->id,
                        'fixed_asset_id' => $asset->id,
                        'period_start' => $periodStart->toDateString(),
                        'period_end' => $periodEnd->toDateString(),
                        'opening_book_value' => $openingBookValue,
                        'depreciation_amount' => $actualDepreciation,
                        'closing_book_value' => $closingBookValue,
                    ]);

                    $totalDepreciation += $actualDepreciation;
                    $lineCount++;
                }

                $run->update([
                    'status' => DepreciationRun::STATUS_PENDING,
                    'total_assets' => $lineCount,
                    'total_depreciation' => round($totalDepreciation, 4),
                ]);
            } catch (\Throwable $e) {
                $run->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                throw $e;
            }

            return $run->fresh(['lines.asset']);
        });
    }

    /**
     * Post a depreciation run: create journal entries, update asset book values,
     * and mark the run as posted.
     */
    public function postDepreciationRun(DepreciationRun $run, int $userId): DepreciationRun
    {
        if ($run->status !== DepreciationRun::STATUS_PENDING) {
            throw new InvalidArgumentException(
                'Only pending depreciation runs can be posted. Current status: ' . $run->status
            );
        }

        return DB::transaction(function () use ($run, $userId): DepreciationRun {
            $run->update(['status' => DepreciationRun::STATUS_PROCESSING]);

            foreach ($run->lines()->with('asset.category')->get() as $line) {
                $asset = $line->asset;
                $category = $asset->category;

                $journalEntry = null;

                // Only create journal entries when GL accounts are configured on the category
                if (
                    $category !== null
                    && $category->gl_depreciation_account_id !== null
                    && $category->gl_accumulated_account_id !== null
                ) {
                    $journalEntry = $this->journalService->createEntry(
                        [
                            'organization_id' => $run->organization_id,
                            'fiscal_year_id' => $run->fiscal_year_id,
                            'entry_date' => $run->period_end->toDateString(),
                            'description' => "Depreciation for {$asset->name} ({$asset->asset_number}) "
                                . "period {$run->period_start->format('Y-m-d')} to {$run->period_end->format('Y-m-d')}",
                            'reference' => "DEP-{$run->id}-{$asset->asset_number}",
                            'created_by' => $userId,
                        ],
                        [
                            [
                                'account_id' => $category->gl_depreciation_account_id,
                                'description' => "Depreciation expense: {$asset->name}",
                                'debit' => (float) $line->depreciation_amount,
                                'credit' => 0,
                                'line_order' => 0,
                            ],
                            [
                                'account_id' => $category->gl_accumulated_account_id,
                                'description' => "Accumulated depreciation: {$asset->name}",
                                'debit' => 0,
                                'credit' => (float) $line->depreciation_amount,
                                'line_order' => 1,
                            ],
                        ]
                    );

                    $this->journalService->postEntry($journalEntry);
                }

                // Update the run line with the journal entry reference
                $line->update([
                    'journal_entry_id' => $journalEntry?->id,
                ]);

                // Update asset accumulated depreciation, book value, and last depreciation date
                $newAccumulated = (float) $asset->accumulated_depreciation + (float) $line->depreciation_amount;
                $asset->update([
                    'accumulated_depreciation' => round($newAccumulated, 4),
                    'book_value' => (float) $line->closing_book_value,
                    'last_depreciation_date' => $run->period_end->toDateString(),
                ]);

                // Record a depreciation transaction on the asset
                AssetTransaction::create([
                    'organization_id' => $run->organization_id,
                    'fixed_asset_id' => $asset->id,
                    'transaction_type' => AssetTransaction::TYPE_DEPRECIATION,
                    'transaction_date' => $run->period_end->toDateString(),
                    'amount' => (float) $line->depreciation_amount,
                    'description' => "Depreciation run #{$run->id}: period {$run->period_start->format('Y-m-d')} to {$run->period_end->format('Y-m-d')}",
                    'journal_entry_id' => $journalEntry?->id,
                    'created_by' => $userId,
                ]);
            }

            $run->update([
                'status' => DepreciationRun::STATUS_POSTED,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            return $run->fresh(['lines.asset', 'lines.journalEntry', 'postedBy']);
        });
    }

    /**
     * Dispose of an asset (full disposal).
     *
     * $data keys: disposal_date, disposal_amount, disposal_reason, notes
     */
    public function disposeAsset(FixedAsset $asset, array $data, int $userId): FixedAsset
    {
        if ($asset->status === FixedAsset::STATUS_DISPOSED) {
            throw new InvalidArgumentException('Asset is already disposed.');
        }

        if ($asset->status === FixedAsset::STATUS_WRITTEN_OFF) {
            throw new InvalidArgumentException('Written-off assets cannot be disposed.');
        }

        return DB::transaction(function () use ($asset, $data, $userId): FixedAsset {
            $disposalDate = $data['disposal_date'];
            $disposalAmount = (float) ($data['disposal_amount'] ?? 0);
            $bookValue = (float) $asset->book_value;

            // Determine gain/loss
            $gainLoss = $disposalAmount - $bookValue;

            $journalEntry = null;
            $category = $asset->category;

            if (
                $category !== null
                && $category->gl_asset_account_id !== null
                && $category->gl_accumulated_account_id !== null
            ) {
                $lines = [];
                $lineOrder = 0;

                // Debit: remove accumulated depreciation
                if ((float) $asset->accumulated_depreciation > 0) {
                    $lines[] = [
                        'account_id' => $category->gl_accumulated_account_id,
                        'description' => "Remove accumulated depreciation: {$asset->name}",
                        'debit' => (float) $asset->accumulated_depreciation,
                        'credit' => 0,
                        'line_order' => $lineOrder++,
                    ];
                }

                // Credit: remove original asset cost
                $lines[] = [
                    'account_id' => $category->gl_asset_account_id,
                    'description' => "Dispose asset: {$asset->name}",
                    'debit' => 0,
                    'credit' => (float) $asset->acquisition_cost,
                    'line_order' => $lineOrder++,
                ];

                // Fix 3: Debit proceeds received to the bank/cash account (not the asset account).
                if ($disposalAmount > 0) {
                    $orgId = $asset->organization_id;
                    $bankCashAccount = Account::where('organization_id', $orgId)
                        ->where(function ($q) {
                            $q->where('account_type', 'bank')
                              ->orWhere('account_type', 'cash');
                        })
                        ->orderBy('id')
                        ->first();

                    if ($bankCashAccount === null) {
                        // Fallback: try account with name LIKE '%Cash%'
                        $bankCashAccount = Account::where('organization_id', $orgId)
                            ->where('name', 'like', '%Cash%')
                            ->orderBy('id')
                            ->first();
                    }

                    if ($bankCashAccount === null) {
                        throw new \App\Exceptions\ApiException(
                            'No bank or cash account configured. Please set up a bank/cash account before recording asset disposals.'
                        );
                    }

                    $proceedsAccountId = $bankCashAccount->id;

                    $lines[] = [
                        'account_id' => $proceedsAccountId,
                        'description' => "Disposal proceeds: {$asset->name}",
                        'debit' => $disposalAmount,
                        'credit' => 0,
                        'line_order' => $lineOrder++,
                    ];
                }

                // Record gain or loss on disposal using depreciation account as proxy
                if (abs($gainLoss) > 0.0001) {
                    $lines[] = [
                        'account_id' => $category->gl_depreciation_account_id ?? $category->gl_asset_account_id,
                        'description' => $gainLoss >= 0
                            ? "Gain on disposal: {$asset->name}"
                            : "Loss on disposal: {$asset->name}",
                        'debit' => $gainLoss < 0 ? abs($gainLoss) : 0,
                        'credit' => $gainLoss >= 0 ? $gainLoss : 0,
                        'line_order' => $lineOrder,
                    ];
                }

                $journalEntry = $this->journalService->createEntry(
                    [
                        'organization_id' => $asset->organization_id,
                        'entry_date' => $disposalDate,
                        'description' => "Disposal of asset {$asset->name} ({$asset->asset_number})",
                        'reference' => "DISP-{$asset->asset_number}",
                        'created_by' => $userId,
                    ],
                    $lines
                );

                $this->journalService->postEntry($journalEntry);
            }

            // Record the disposal transaction
            AssetTransaction::create([
                'organization_id' => $asset->organization_id,
                'fixed_asset_id' => $asset->id,
                'transaction_type' => AssetTransaction::TYPE_FULL_DISPOSAL,
                'transaction_date' => $disposalDate,
                'amount' => $disposalAmount,
                'description' => $data['disposal_reason'] ?? "Full disposal of {$asset->name}",
                'journal_entry_id' => $journalEntry?->id,
                'created_by' => $userId,
            ]);

            $asset->update([
                'status' => FixedAsset::STATUS_DISPOSED,
                'disposal_date' => $disposalDate,
                'disposal_amount' => $disposalAmount,
                'disposal_reason' => $data['disposal_reason'] ?? null,
                'notes' => $data['notes'] ?? $asset->notes,
            ]);

            return $asset->fresh(['category', 'transactions']);
        });
    }

    /**
     * Return a future depreciation schedule for the remaining useful life of the asset.
     *
     * Each row: ['period' => 'YYYY-MM', 'depreciation' => float, 'book_value' => float]
     */
    public function getDepreciationSchedule(FixedAsset $asset): array
    {
        if ($asset->isFullyDepreciated()) {
            return [];
        }

        $schedule = [];
        $bookValue = (float) $asset->book_value;
        $salvageValue = (float) $asset->salvage_value;

        // Start from the month after the last depreciation date, or from acquisition date
        $baseDate = $asset->last_depreciation_date !== null
            ? $asset->last_depreciation_date->copy()->addMonth()
            : $asset->acquisition_date->copy();

        // Calculate remaining months of useful life
        $totalLifeMonths = (int) round((float) $asset->useful_life_years * 12);
        $elapsedMonths = $asset->last_depreciation_date !== null
            ? (int) $asset->acquisition_date->diffInMonths($asset->last_depreciation_date)
            : 0;
        $remainingMonths = max(0, $totalLifeMonths - $elapsedMonths);

        if ($remainingMonths === 0) {
            return [];
        }

        $currentDate = $baseDate->copy()->startOfMonth();

        for ($month = 0; $month < $remainingMonths; $month++) {
            if ($bookValue <= $salvageValue) {
                break;
            }

            // Temporarily simulate book_value for calculation purposes
            $tempAsset = clone $asset;
            $tempAsset->book_value = $bookValue;

            $depreciation = $tempAsset->calculatePeriodicDepreciation(1);

            if ($depreciation <= 0) {
                break;
            }

            $newBookValue = bcsub((string)$bookValue, (string)$depreciation, 4);
            $bookValue = bccomp($newBookValue, (string)$salvageValue, 4) < 0 ? (string)$salvageValue : $newBookValue;

            $schedule[] = [
                'period' => $currentDate->format('Y-m'),
                'depreciation' => $depreciation,
                'book_value' => $bookValue,
            ];

            $currentDate->addMonth();
        }

        return $schedule;
    }

    // -------------------------------------------------------------------------
    // AuC Settlement (SAP AIAB/AIBU)
    // -------------------------------------------------------------------------

    /**
     * Settle an Asset Under Construction (AuC) to a final fixed asset.
     *
     * Creates an AssetTransaction TYPE_TRANSFER on the AuC (reducing its book value)
     * and TYPE_ACQUISITION on the target asset. Posts a GL journal entry:
     *   DR  Target asset account
     *   CR  AuC asset account
     *
     * When the AuC is fully settled (book_value reaches 0), it is marked as disposed.
     *
     * @throws InvalidArgumentException if source is not an AuC, already settled, or amount exceeds book value
     */
    public function settleAuC(
        FixedAsset $aucAsset,
        FixedAsset $targetAsset,
        float $amount,
        string $settlementDate,
        int $userId
    ): array {
        if (! $aucAsset->is_auc) {
            throw new InvalidArgumentException("Asset [{$aucAsset->asset_number}] is not flagged as AuC.");
        }

        if ($aucAsset->auc_settled_at !== null) {
            throw new InvalidArgumentException("AuC [{$aucAsset->asset_number}] is already fully settled.");
        }

        $bookValue = (float) $aucAsset->book_value;

        if ($amount <= 0 || $amount > $bookValue + 0.0001) {
            throw new InvalidArgumentException(
                "Settlement amount {$amount} exceeds AuC book value {$bookValue}."
            );
        }

        return DB::transaction(function () use ($aucAsset, $targetAsset, $amount, $settlementDate, $userId): array {
            // 1. Transfer transaction on AuC (deduction)
            AssetTransaction::create([
                'organization_id'  => $aucAsset->organization_id,
                'fixed_asset_id'   => $aucAsset->id,
                'transaction_type' => AssetTransaction::TYPE_TRANSFER,
                'transaction_date' => $settlementDate,
                'amount'           => -$amount,
                'description'      => "AuC settlement to asset {$targetAsset->asset_number}",
                'created_by'       => $userId,
            ]);

            $newAucBookValue = (float) $aucAsset->book_value - $amount;

            $aucAsset->book_value            = max(0.0, $newAucBookValue);
            $aucAsset->auc_settled_amount    = (float) $aucAsset->auc_settled_amount + $amount;

            if ($aucAsset->book_value <= 0.0001) {
                $aucAsset->auc_settled_at = now();
                $aucAsset->status         = FixedAsset::STATUS_DISPOSED;
            }

            $aucAsset->save();

            // 2. Acquisition transaction on target asset
            AssetTransaction::create([
                'organization_id'  => $targetAsset->organization_id,
                'fixed_asset_id'   => $targetAsset->id,
                'transaction_type' => AssetTransaction::TYPE_ACQUISITION,
                'transaction_date' => $settlementDate,
                'amount'           => $amount,
                'description'      => "Settlement from AuC {$aucAsset->asset_number}",
                'created_by'       => $userId,
            ]);

            $targetAsset->acquisition_cost = (float) $targetAsset->acquisition_cost + $amount;
            $targetAsset->book_value       = (float) $targetAsset->book_value + $amount;
            $targetAsset->save();

            // 3. GL journal entry
            $aucCategory    = $aucAsset->category;
            $targetCategory = $targetAsset->category;

            $journalEntry = null;

            if ($aucCategory?->gl_asset_account_id && $targetCategory?->gl_asset_account_id) {
                $entry = $this->journalService->createSimpleEntry(
                    organizationId: $aucAsset->organization_id,
                    branchId:       $aucAsset->branch_id ?? 1,
                    debitAccountId: $targetCategory->gl_asset_account_id,
                    creditAccountId: $aucCategory->gl_asset_account_id,
                    amount:         $amount,
                    description:    "AuC settlement {$aucAsset->asset_number} → {$targetAsset->asset_number}",
                    reference:      "AUC-SETTLE-{$aucAsset->asset_number}",
                    date:           $settlementDate
                );
                $journalEntry = $this->journalService->postEntry($entry);
            }

            return [
                'auc_asset'         => $aucAsset->fresh(),
                'target_asset'      => $targetAsset->fresh(),
                'settled_amount'    => $amount,
                'auc_remaining'     => (float) $aucAsset->book_value,
                'fully_settled'     => $aucAsset->auc_settled_at !== null,
                'journal_entry_id'  => $journalEntry?->id,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // Asset Category helpers
    // -------------------------------------------------------------------------

    public function createCategory(array $data): AssetCategory
    {
        return AssetCategory::create($data);
    }

    public function updateCategory(AssetCategory $category, array $data): AssetCategory
    {
        $category->update($data);

        return $category->fresh();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function generateAssetNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $prefix = "FA-{$year}-";

        $last = FixedAsset::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('asset_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('asset_number');

        $sequence = $last !== null ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
