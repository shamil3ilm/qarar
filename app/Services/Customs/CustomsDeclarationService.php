<?php

declare(strict_types=1);

namespace App\Services\Customs;

use App\Models\Customs\CustomsDeclaration;
use App\Models\Customs\CustomsDeclarationItem;
use App\Models\Customs\CustomsTariffCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomsDeclarationService
{
    /**
     * Create a new customs declaration.
     */
    public function create(array $data): CustomsDeclaration
    {
        return DB::transaction(function () use ($data) {
            $declaration = CustomsDeclaration::create($data);

            if (!empty($data['items'])) {
                $this->addItems($declaration, $data['items']);
            }

            return $declaration->fresh(['items', 'importerExporter', 'broker']);
        });
    }

    /**
     * Update an existing customs declaration.
     */
    public function update(CustomsDeclaration $declaration, array $data): CustomsDeclaration
    {
        if (!$declaration->isEditable()) {
            throw new InvalidArgumentException('Only draft declarations can be updated.');
        }

        return DB::transaction(function () use ($declaration, $data) {
            $declaration->update($data);

            if (isset($data['items'])) {
                $declaration->items()->delete();
                $this->addItems($declaration, $data['items']);
            }

            return $declaration->fresh(['items', 'importerExporter', 'broker']);
        });
    }

    /**
     * Add items to a customs declaration.
     */
    public function addItems(CustomsDeclaration $declaration, array $items): CustomsDeclaration
    {
        return DB::transaction(function () use ($declaration, $items) {
            foreach ($items as $index => $itemData) {
                $itemData['declaration_id'] = $declaration->id;
                $itemData['item_number'] = $itemData['item_number'] ?? $index + 1;

                // Auto-fill tariff data if tariff_id provided
                if (!empty($itemData['tariff_id']) && empty($itemData['duty_rate'])) {
                    $tariff = CustomsTariffCode::find($itemData['tariff_id']);
                    if ($tariff) {
                        $itemData['tariff_code'] = $itemData['tariff_code'] ?? $tariff->code;
                        $itemData['duty_rate'] = $itemData['duty_rate'] ?? $tariff->duty_rate_percent;
                        $itemData['excise_rate'] = $itemData['excise_rate'] ?? ($tariff->excise_rate ?? 0);
                    }
                }

                $item = new CustomsDeclarationItem($itemData);
                $item->calculateTaxes();
                $item->save();
            }

            $declaration->recalculateTotals();

            return $declaration->fresh(['items']);
        });
    }

    /**
     * Submit a declaration for assessment.
     */
    public function submit(CustomsDeclaration $declaration): CustomsDeclaration
    {
        if (!$declaration->canSubmit()) {
            throw new InvalidArgumentException('Declaration cannot be submitted. Ensure it is in draft status and has items.');
        }

        return DB::transaction(function () use ($declaration) {
            $declaration->update([
                'status' => CustomsDeclaration::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Assess a submitted declaration (customs officer assessment).
     */
    public function assess(CustomsDeclaration $declaration, array $assessmentData = []): CustomsDeclaration
    {
        if (!$declaration->canAssess()) {
            throw new InvalidArgumentException('Only submitted declarations can be assessed.');
        }

        return DB::transaction(function () use ($declaration, $assessmentData) {
            // Update assessable values if provided
            if (!empty($assessmentData['items'])) {
                foreach ($assessmentData['items'] as $itemUpdate) {
                    $item = $declaration->items()->find($itemUpdate['id']);
                    if ($item) {
                        $item->update($itemUpdate);
                        $item->calculateTaxes();
                        $item->save();
                    }
                }
            }

            if (isset($assessmentData['assessable_value'])) {
                $declaration->assessable_value = $assessmentData['assessable_value'];
            }

            $declaration->recalculateTotals();

            $declaration->update([
                'status' => CustomsDeclaration::STATUS_ASSESSED,
                'assessed_at' => now(),
            ]);

            return $declaration->fresh(['items']);
        });
    }

    /**
     * Record duty payment.
     */
    public function payDuty(CustomsDeclaration $declaration, array $paymentData = []): CustomsDeclaration
    {
        if (!$declaration->canPayDuty()) {
            throw new InvalidArgumentException('Only assessed declarations can have duty paid.');
        }

        return DB::transaction(function () use ($declaration, $paymentData) {
            $updateData = [
                'status' => CustomsDeclaration::STATUS_DUTY_PAID,
                'duty_paid_at' => now(),
            ];

            if (isset($paymentData['journal_entry_id'])) {
                $updateData['journal_entry_id'] = $paymentData['journal_entry_id'];
            }

            $declaration->update($updateData);

            return $declaration->fresh();
        });
    }

    /**
     * Clear a declaration (goods released).
     */
    public function clear(CustomsDeclaration $declaration): CustomsDeclaration
    {
        if (!$declaration->canClear()) {
            throw new InvalidArgumentException('Only duty-paid declarations can be cleared.');
        }

        return DB::transaction(function () use ($declaration) {
            $declaration->update([
                'status' => CustomsDeclaration::STATUS_CLEARED,
                'cleared_at' => now(),
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Reject a declaration.
     */
    public function reject(CustomsDeclaration $declaration, string $reason): CustomsDeclaration
    {
        if (!in_array($declaration->status, [CustomsDeclaration::STATUS_SUBMITTED, CustomsDeclaration::STATUS_ASSESSED])) {
            throw new InvalidArgumentException('Only submitted or assessed declarations can be rejected.');
        }

        return DB::transaction(function () use ($declaration, $reason) {
            $declaration->update([
                'status' => CustomsDeclaration::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Cancel a declaration.
     */
    public function cancel(CustomsDeclaration $declaration): CustomsDeclaration
    {
        if (in_array($declaration->status, [CustomsDeclaration::STATUS_CLEARED, CustomsDeclaration::STATUS_CANCELLED])) {
            throw new InvalidArgumentException('Cleared or already cancelled declarations cannot be cancelled.');
        }

        return DB::transaction(function () use ($declaration) {
            $declaration->update([
                'status' => CustomsDeclaration::STATUS_CANCELLED,
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Calculate duties for a set of items based on tariff codes.
     */
    public function calculateDuties(array $items, float $vatRate = 5.0): array
    {
        $results = [];
        $totals = ['duty' => 0, 'vat' => 0, 'excise' => 0, 'cess' => 0, 'total' => 0];

        foreach ($items as $item) {
            $tariff = null;
            if (!empty($item['tariff_id'])) {
                $tariff = CustomsTariffCode::find($item['tariff_id']);
            } elseif (!empty($item['tariff_code'])) {
                $tariff = CustomsTariffCode::where('code', $item['tariff_code'])->first();
            }

            $assessableValue = (float) ($item['assessable_value'] ?? $item['total_value'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);

            $dutyRate = $tariff ? (float) $tariff->duty_rate_percent : (float) ($item['duty_rate'] ?? 0);
            $exciseRate = $tariff ? (float) ($tariff->excise_rate ?? 0) : (float) ($item['excise_rate'] ?? 0);

            $dutyAmount = $tariff
                ? $tariff->calculateDuty($assessableValue, $quantity)
                : round($assessableValue * $dutyRate / 100, 4);

            $exciseAmount = round(($assessableValue + $dutyAmount) * $exciseRate / 100, 4);
            $vatAmount = round(($assessableValue + $dutyAmount + $exciseAmount) * $vatRate / 100, 4);

            $result = [
                'description' => $item['description'] ?? '',
                'assessable_value' => $assessableValue,
                'duty_rate' => $dutyRate,
                'duty_amount' => $dutyAmount,
                'excise_rate' => $exciseRate,
                'excise_amount' => $exciseAmount,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'total_taxes' => $dutyAmount + $vatAmount + $exciseAmount,
            ];

            $results[] = $result;
            $totals['duty'] += $dutyAmount;
            $totals['vat'] += $vatAmount;
            $totals['excise'] += $exciseAmount;
            $totals['total'] += $result['total_taxes'];
        }

        return [
            'items' => $results,
            'totals' => $totals,
        ];
    }

    /**
     * Get declarations by status.
     */
    public function getByStatus(string $status, int $perPage = 20): LengthAwarePaginator
    {
        return CustomsDeclaration::with(['importerExporter:id,name', 'createdBy:id,name'])
            ->forStatus($status)
            ->orderByDesc('declaration_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
