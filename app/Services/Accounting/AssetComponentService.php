<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\AssetComponent;
use App\Models\Accounting\AssetTransaction;
use App\Models\Accounting\FixedAsset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * FI-AA Component Accounting (SAP AA sub-ledger component tracking).
 *
 * Allows individual components of a fixed asset to be tracked, depreciated,
 * and retired independently — for example, replacing the engine of a vehicle
 * without retiring the whole asset.
 */
class AssetComponentService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Add a component to an existing fixed asset.
     *
     * The parent asset's acquisition cost and book value are increased by the
     * component cost, keeping the sub-ledger consistent with the GL asset account.
     */
    public function addComponent(FixedAsset $asset, array $data, int $userId): AssetComponent
    {
        if (! in_array($asset->status, [FixedAsset::STATUS_ACTIVE, FixedAsset::STATUS_UNDER_MAINTENANCE], true)) {
            throw new InvalidArgumentException('Components can only be added to active assets.');
        }

        return DB::transaction(function () use ($asset, $data, $userId): AssetComponent {
            $cost = (float) $data['acquisition_cost'];

            $component = AssetComponent::create([
                'organization_id'    => $asset->organization_id,
                'fixed_asset_id'     => $asset->id,
                'component_number'   => $data['component_number'] ?? $this->generateComponentNumber($asset),
                'name'               => $data['name'],
                'description'        => $data['description'] ?? null,
                'acquisition_date'   => $data['acquisition_date'],
                'acquisition_cost'   => $cost,
                'salvage_value'      => (float) ($data['salvage_value'] ?? 0),
                'useful_life_years'  => (float) ($data['useful_life_years'] ?? $asset->useful_life_years),
                'accumulated_depreciation' => 0,
                'book_value'         => $cost,
                'depreciation_method' => $data['depreciation_method'] ?? $asset->depreciation_method,
                'status'             => AssetComponent::STATUS_ACTIVE,
                'created_by'         => $userId,
            ]);

            // Increase parent asset cost and book value
            $asset->acquisition_cost = (float) $asset->acquisition_cost + $cost;
            $asset->book_value       = (float) $asset->book_value + $cost;
            $asset->save();

            // Record acquisition transaction on the parent
            AssetTransaction::create([
                'organization_id'  => $asset->organization_id,
                'fixed_asset_id'   => $asset->id,
                'transaction_type' => AssetTransaction::TYPE_ACQUISITION,
                'transaction_date' => $data['acquisition_date'],
                'amount'           => $cost,
                'description'      => "Component added: {$component->name} ({$component->component_number})",
                'created_by'       => $userId,
            ]);

            return $component->fresh();
        });
    }

    /**
     * Retire (partially dispose) a single asset component.
     *
     * Reduces the parent asset's acquisition cost and accumulated depreciation
     * by the component amounts, then marks the component as retired.
     * Posts a GL journal entry when GL accounts are configured.
     */
    public function retireComponent(
        AssetComponent $component,
        float $proceedsAmount,
        string $retirementDate,
        string $reason,
        int $userId
    ): AssetComponent {
        if ($component->status !== AssetComponent::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Only active components can be retired.');
        }

        return DB::transaction(function () use ($component, $proceedsAmount, $retirementDate, $reason, $userId): AssetComponent {
            $asset = $component->fixedAsset;
            $componentCost = (float) $component->acquisition_cost;
            $componentAccumDep = (float) $component->accumulated_depreciation;
            $componentBookValue = (float) $component->book_value;
            $gainLoss = $proceedsAmount - $componentBookValue;

            // Build GL journal if the asset category has GL accounts configured
            $journalEntry = null;
            $category = $asset->category;

            if ($category?->gl_asset_account_id && $category?->gl_accumulated_account_id) {
                $lines = [];
                $seq = 0;

                if ($componentAccumDep > 0) {
                    $lines[] = [
                        'account_id'  => $category->gl_accumulated_account_id,
                        'description' => "Remove accumulated depreciation: {$component->name}",
                        'debit'       => $componentAccumDep,
                        'credit'      => 0,
                        'line_order'  => $seq++,
                    ];
                }

                $lines[] = [
                    'account_id'  => $category->gl_asset_account_id,
                    'description' => "Retire component: {$component->name}",
                    'debit'       => 0,
                    'credit'      => $componentCost,
                    'line_order'  => $seq++,
                ];

                if ($proceedsAmount > 0) {
                    $lines[] = [
                        'account_id'  => $category->gl_asset_account_id,
                        'description' => "Proceeds from component retirement",
                        'debit'       => $proceedsAmount,
                        'credit'      => 0,
                        'line_order'  => $seq++,
                    ];
                }

                if (abs($gainLoss) > 0.0001) {
                    $gainAccountId = $category->gl_depreciation_account_id ?? $category->gl_asset_account_id;
                    $lines[] = [
                        'account_id'  => $gainAccountId,
                        'description' => $gainLoss >= 0
                            ? "Gain on component retirement: {$component->name}"
                            : "Loss on component retirement: {$component->name}",
                        'debit'       => $gainLoss < 0 ? abs($gainLoss) : 0,
                        'credit'      => $gainLoss >= 0 ? $gainLoss : 0,
                        'line_order'  => $seq,
                    ];
                }

                $journalEntry = $this->journalService->createEntry(
                    [
                        'organization_id' => $asset->organization_id,
                        'entry_date'      => $retirementDate,
                        'description'     => "Component retirement: {$component->name} ({$component->component_number})",
                        'reference'       => "COMP-RET-{$component->component_number}",
                        'created_by'      => $userId,
                    ],
                    $lines
                );

                $this->journalService->postEntry($journalEntry);
            }

            // Mark component as retired
            $component->update([
                'status'             => AssetComponent::STATUS_RETIRED,
                'retirement_date'    => $retirementDate,
                'retirement_amount'  => $proceedsAmount,
                'retirement_reason'  => $reason,
                'journal_entry_id'   => $journalEntry?->id,
            ]);

            // Reduce parent asset cost and accumulated depreciation
            $asset->acquisition_cost       = max(0.0, (float) $asset->acquisition_cost - $componentCost);
            $asset->accumulated_depreciation = max(0.0, (float) $asset->accumulated_depreciation - $componentAccumDep);
            $asset->book_value             = max(0.0, (float) $asset->book_value - $componentBookValue);
            $asset->save();

            // Record partial disposal on parent
            AssetTransaction::create([
                'organization_id'  => $asset->organization_id,
                'fixed_asset_id'   => $asset->id,
                'transaction_type' => AssetTransaction::TYPE_PARTIAL_DISPOSAL,
                'transaction_date' => $retirementDate,
                'amount'           => $componentCost,
                'description'      => "Component retired: {$component->name} ({$component->component_number}) — {$reason}",
                'journal_entry_id' => $journalEntry?->id,
                'created_by'       => $userId,
            ]);

            return $component->fresh();
        });
    }

    /**
     * Generate a sequential component number for the given asset (e.g. FA-2026-000001-C001).
     */
    private function generateComponentNumber(FixedAsset $asset): string
    {
        $prefix = $asset->asset_number . '-C';
        $last = AssetComponent::where('fixed_asset_id', $asset->id)
            ->where('component_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('component_number');

        $seq = $last !== null ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
