<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Exceptions\ApiException;
use App\Models\Purchase\ServiceAcceptance;
use App\Models\Purchase\ServiceEntrySheet;
use App\Models\Purchase\ServiceEntrySheetLine;
use App\Models\Purchase\ServicePoLine;
use App\Models\Purchase\ServicePurchaseOrder;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

class ServiceProcurementService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Create a new Service Purchase Order with its lines.
     */
    public function createServicePO(array $data): ServicePurchaseOrder
    {
        return DB::transaction(function () use ($data): ServicePurchaseOrder {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if (empty($data['po_number'])) {
                $data['po_number'] = $this->numberGenerator->generate('SVC-PO');
            }

            $data['status'] = ServicePurchaseOrder::STATUS_DRAFT;
            $data['created_by'] = auth()->id();

            $po = ServicePurchaseOrder::create($data);

            $totalValue = '0';
            foreach ($lines as $index => $lineData) {
                $lineData['line_number'] = $lineData['line_number'] ?? ($index + 1);
                $lineData['total_price'] = bcmul(
                    (string) $lineData['quantity'],
                    (string) $lineData['unit_price'],
                    4
                );
                $po->lines()->create($lineData);
                $totalValue = bcadd($totalValue, (string) $lineData['total_price'], 4);
            }

            $po->update(['total_value' => $totalValue]);

            return $po->load(['vendor', 'lines', 'creator']);
        });
    }

    /**
     * Submit a Service Entry Sheet against a Service PO.
     */
    public function submitSES(array $data): ServiceEntrySheet
    {
        return DB::transaction(function () use ($data): ServiceEntrySheet {
            $po = ServicePurchaseOrder::findOrFail($data['service_purchase_order_id']);

            if (!in_array($po->status, [ServicePurchaseOrder::STATUS_SENT, ServicePurchaseOrder::STATUS_PARTIALLY_ACCEPTED], true)) {
                throw new ApiException(
                    'Service PO must be in sent or partially_accepted status to create an entry sheet.',
                    422
                );
            }

            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if (empty($data['ses_number'])) {
                $data['ses_number'] = $this->numberGenerator->generate('SES');
            }

            $data['vendor_id'] = $data['vendor_id'] ?? $po->vendor_id;
            $data['status'] = ServiceEntrySheet::STATUS_SUBMITTED;
            $data['submitted_by'] = auth()->id();

            $ses = ServiceEntrySheet::create($data);

            foreach ($lines as $lineData) {
                $poLine = ServicePoLine::where('service_purchase_order_id', $po->id)
                    ->findOrFail($lineData['service_po_line_id']);

                $remaining = $poLine->getRemainingQuantity();
                if (bccomp((string) $lineData['actual_quantity'], $remaining, 4) > 0) {
                    throw new ApiException(
                        "Quantity for line {$poLine->line_number} exceeds remaining quantity ({$remaining}).",
                        422
                    );
                }

                $lineData['total_amount'] = bcmul(
                    (string) $lineData['actual_quantity'],
                    (string) $lineData['actual_price'],
                    4
                );

                $ses->lines()->create($lineData);
            }

            return $ses->load(['servicePurchaseOrder', 'vendor', 'lines']);
        });
    }

    /**
     * Approve a Service Entry Sheet.
     */
    public function approveSES(ServiceEntrySheet $ses): ServiceEntrySheet
    {
        return DB::transaction(function () use ($ses): ServiceEntrySheet {
            if (!$ses->canBeApproved()) {
                throw new ApiException(
                    "SES #{$ses->ses_number} is not in submitted status and cannot be approved.",
                    422
                );
            }

            $ses->update([
                'status' => ServiceEntrySheet::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $ses->fresh(['submitter', 'approver', 'lines']);
        });
    }

    /**
     * Accept a Service Entry Sheet (formal service acceptance).
     */
    public function acceptService(ServiceEntrySheet $ses, int $userId): ServiceAcceptance
    {
        return DB::transaction(function () use ($ses, $userId): ServiceAcceptance {
            if ($ses->status !== ServiceEntrySheet::STATUS_APPROVED) {
                throw new ApiException(
                    "SES #{$ses->ses_number} must be approved before acceptance.",
                    422
                );
            }

            if ($ses->acceptance()->exists()) {
                throw new ApiException(
                    "SES #{$ses->ses_number} has already been accepted.",
                    422
                );
            }

            $acceptance = ServiceAcceptance::create([
                'organization_id' => $ses->organization_id,
                'service_entry_sheet_id' => $ses->id,
                'accepted_by' => $userId,
                'accepted_at' => now(),
                'status' => ServiceAcceptance::STATUS_ACCEPTED,
            ]);

            // Update SES to posted
            $ses->update([
                'status' => ServiceEntrySheet::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            // Update accepted_quantity on each PO line
            foreach ($ses->lines as $sesLine) {
                $poLine = $sesLine->poLine;
                $newAccepted = bcadd(
                    (string) $poLine->accepted_quantity,
                    (string) $sesLine->actual_quantity,
                    4
                );
                $poLine->update(['accepted_quantity' => $newAccepted]);
            }

            // Update PO status
            $po = $ses->servicePurchaseOrder;
            $allLinesFullyAccepted = $po->lines->every(
                fn(ServicePoLine $line) => bccomp(
                    (string) $line->fresh()->accepted_quantity,
                    (string) $line->quantity,
                    4
                ) >= 0
            );

            $po->update([
                'status' => $allLinesFullyAccepted
                    ? ServicePurchaseOrder::STATUS_ACCEPTED
                    : ServicePurchaseOrder::STATUS_PARTIALLY_ACCEPTED,
            ]);

            return $acceptance->load('entrySheet', 'acceptedBy');
        });
    }

    /**
     * Send a Service PO to the vendor.
     */
    public function sendServicePO(ServicePurchaseOrder $po): ServicePurchaseOrder
    {
        if ($po->status !== ServicePurchaseOrder::STATUS_DRAFT) {
            throw new ApiException(
                "Service PO #{$po->po_number} must be in draft status to send.",
                422
            );
        }

        $po->update(['status' => ServicePurchaseOrder::STATUS_SENT]);

        return $po->fresh();
    }

    /**
     * Reject a Service Entry Sheet.
     */
    public function rejectSES(ServiceEntrySheet $ses, string $reason): ServiceEntrySheet
    {
        if ($ses->status !== ServiceEntrySheet::STATUS_SUBMITTED) {
            throw new ApiException(
                "SES #{$ses->ses_number} must be in submitted status to reject.",
                422
            );
        }

        $ses->update([
            'status' => ServiceEntrySheet::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $ses->fresh();
    }
}
