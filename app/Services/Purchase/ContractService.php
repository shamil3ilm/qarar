<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Contract;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContractService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Create a new contract with lines.
     */
    public function createContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['contract_number'])) {
                $data['contract_number'] = $this->numberGenerator->generate('CON');
            }

            $lines = $data['lines'] ?? [];
            $milestones = $data['milestones'] ?? [];
            unset($data['lines'], $data['milestones']);

            $data['created_by'] = $data['created_by'] ?? auth()->id();
            $data['status'] = Contract::STATUS_DRAFT;

            $contract = Contract::create($data);

            foreach ($lines as $index => $lineData) {
                $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                $contract->lines()->create($lineData);
            }

            foreach ($milestones as $milestoneData) {
                $contract->milestones()->create($milestoneData);
            }

            return $contract->load(['lines', 'milestones', 'contact']);
        });
    }

    /**
     * Activate a draft contract.
     */
    public function activateContract(Contract $contract): Contract
    {
        if (!$contract->canBeActivated()) {
            throw new \InvalidArgumentException('Only draft contracts can be activated.');
        }

        $contract->update([
            'status' => Contract::STATUS_ACTIVE,
            'signed_date' => $contract->signed_date ?? now()->toDateString(),
        ]);

        return $contract->fresh();
    }

    /**
     * Create a release order against a contract.
     */
    public function createRelease(Contract $contract, array $data): \App\Models\Purchase\ContractRelease
    {
        if (!$contract->isActive()) {
            throw new \InvalidArgumentException('Releases can only be created against active contracts.');
        }

        return DB::transaction(function () use ($contract, $data): \App\Models\Purchase\ContractRelease {
            // Re-fetch with a row lock to prevent concurrent over-release race conditions.
            $contract = Contract::lockForUpdate()->findOrFail($contract->id);

            if ($contract->total_value !== null) {
                $totalReleased = $contract->releases()
                    ->whereIn('status', ['pending', 'fulfilled'])
                    ->sum('amount');
                $newTotal = bcadd((string) $totalReleased, (string) $data['amount'], 4);

                if (bccomp($newTotal, (string) $contract->total_value, 4) > 0) {
                    throw new \InvalidArgumentException('Release amount exceeds remaining contract value.');
                }
            }

            $data['release_date'] = $data['release_date'] ?? now()->toDateString();
            $data['status'] = 'pending';

            return $contract->releases()->create($data);
        });
    }

    /**
     * Terminate a contract.
     */
    public function terminateContract(Contract $contract, array $data): Contract
    {
        if (!$contract->canBeTerminated()) {
            throw new \InvalidArgumentException('Contract cannot be terminated in its current status.');
        }

        $contract->update([
            'status' => Contract::STATUS_TERMINATED,
            'notes' => trim(($contract->notes ?? '') . "\n\nTerminated: " . ($data['reason'] ?? '')),
        ]);

        return $contract->fresh();
    }

    /**
     * Get contracts expiring within the given number of days for an organization.
     */
    public function checkExpiringContracts(Organization $organization, int $days = 30): Collection
    {
        return Contract::where('organization_id', $organization->id)
            ->expiringSoon($days)
            ->with(['contact'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Expire contracts that have passed their end date.
     */
    public function expireOverdueContracts(): int
    {
        return Contract::where('status', Contract::STATUS_ACTIVE)
            ->where('end_date', '<', now()->toDateString())
            ->update(['status' => Contract::STATUS_EXPIRED]);
    }
}
