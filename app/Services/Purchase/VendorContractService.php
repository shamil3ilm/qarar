<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\VendorContract;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorContractService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Create a new vendor contract with optional line items.
     *
     * Expected keys in $data:
     *   organization_id, contact_id, title, description?,
     *   contract_type?, currency_code?, total_value?, start_date,
     *   end_date?, auto_renew?, renewal_notice_days?, payment_terms?,
     *   signed_at?, notes?, created_by?,
     *   items[] => [product_id?, description, unit_price, quantity?, unit_of_measure?]
     */
    public function create(array $data): VendorContract
    {
        return DB::transaction(function () use ($data) {
            $data['contract_number'] = $data['contract_number']
                ?? $this->numberGenerator->generate('VCON');

            $data['status'] = VendorContract::STATUS_DRAFT;

            $contract = VendorContract::create($data);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $contract->items()->create($item);
                }
            }

            return $contract->load('items');
        });
    }

    /**
     * Activate a contract (move from draft to active).
     */
    public function activate(VendorContract $contract): VendorContract
    {
        if ($contract->status !== VendorContract::STATUS_DRAFT) {
            throw new RuntimeException(
                "Only draft contracts can be activated. Current status: {$contract->status}."
            );
        }

        $contract->update(['status' => VendorContract::STATUS_ACTIVE]);

        return $contract->refresh();
    }

    /**
     * Terminate an active contract with a reason.
     */
    public function terminate(VendorContract $contract, string $reason): VendorContract
    {
        if ($contract->status !== VendorContract::STATUS_ACTIVE) {
            throw new RuntimeException(
                "Only active contracts can be terminated. Current status: {$contract->status}."
            );
        }

        $contract->update([
            'status'               => VendorContract::STATUS_TERMINATED,
            'terminated_at'        => now()->toDateString(),
            'termination_reason'   => $reason,
        ]);

        return $contract->refresh();
    }

    /**
     * Return contracts for an organization that expire within $days days.
     */
    public function getExpiringContracts(Organization $org, int $days = 30): Collection
    {
        return VendorContract::where('organization_id', $org->id)
            ->where('status', VendorContract::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '>=', now()->toDateString())
            ->where('end_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('end_date')
            ->get();
    }
}
