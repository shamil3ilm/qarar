<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Models\Sales\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZatcaWebhookController extends Controller
{
    /**
     * Status priority map — higher value means higher priority.
     * A status should never be downgraded to a lower-priority one.
     */
    private const STATUS_PRIORITY = [
        Invoice::COMPLIANCE_NOT_APPLICABLE => 0,
        Invoice::COMPLIANCE_PENDING        => 1,
        Invoice::COMPLIANCE_SUBMITTED      => 2,
        Invoice::COMPLIANCE_REPORTED       => 3,
        Invoice::COMPLIANCE_CLEARED        => 3,
        Invoice::COMPLIANCE_REJECTED       => 4,
    ];

    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $data  = $request->input('data');

        if (empty($event) || !is_array($data)) {
            return $this->unknownEvent($event ?? '');
        }

        $complianceUuid = $data['invoice_uuid'] ?? $data['uuid'] ?? null;

        if (empty($complianceUuid)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVOICE_NOT_FOUND',
                    'message' => 'Invoice UUID not provided in event data',
                ],
            ], 404);
        }

        $organizationId = $data['organization_id'] ?? null;

        if (empty($organizationId)) {
            Log::warning('ZATCA webhook: organization_id missing from payload', [
                'event'           => $event,
                'compliance_uuid' => $complianceUuid,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORGANIZATION_REQUIRED',
                    'message' => 'organization_id is required in event data',
                ],
            ], 422);
        }

        $invoice = Invoice::withoutGlobalScopes()
            ->where('compliance_uuid', $complianceUuid)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$invoice) {
            Log::warning('ZATCA webhook: invoice not found', [
                'event'           => $event,
                'compliance_uuid' => $complianceUuid,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVOICE_NOT_FOUND',
                    'message' => 'Invoice with the given compliance UUID was not found',
                ],
            ], 404);
        }

        return match ($event) {
            'invoice.cleared'  => $this->handleCleared($invoice, $data),
            'invoice.reported' => $this->handleReported($invoice, $data),
            'invoice.rejected' => $this->handleRejected($invoice, $data),
            'invoice.issued'   => $this->handleIssued($invoice, $data),
            default            => $this->unknownEvent($event),
        };
    }

    private function handleCleared(Invoice $invoice, array $data): JsonResponse
    {
        // Capture pre-update status to prevent duplicate notifications on replay
        $wasAlreadyCleared = $invoice->compliance_status === Invoice::COMPLIANCE_CLEARED;

        $response = $this->updateInvoice(
            $invoice,
            Invoice::COMPLIANCE_CLEARED,
            $data,
            'invoice.cleared',
            updateHashAndQr: true
        );

        // Send the deferred customer notification for B2B (standard) invoices.
        // InvoiceService::send() withholds this notification until clearance is confirmed.
        if (!$wasAlreadyCleared && $invoice->invoice_type === Invoice::TYPE_STANDARD) {
            try {
                $fresh = $invoice->fresh(['customer']);

                if ($fresh?->customer?->email) {
                    $fresh->customer->notify(
                        new \App\Notifications\Sales\InvoiceSentNotification($fresh)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('ZATCA webhook: clearance notification failed', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }

    private function handleReported(Invoice $invoice, array $data): JsonResponse
    {
        return $this->updateInvoice(
            $invoice,
            Invoice::COMPLIANCE_REPORTED,
            $data,
            'invoice.reported',
            updateHashAndQr: false
        );
    }

    private function handleRejected(Invoice $invoice, array $data): JsonResponse
    {
        return DB::transaction(function () use ($invoice, $data): JsonResponse {
            $fresh = $invoice->newQuery()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->find($invoice->id);

            if (!$fresh) {
                return response()->json(['success' => true, 'message' => 'Event processed'], 200);
            }

            if ($this->isDowngrade($fresh->compliance_status, Invoice::COMPLIANCE_REJECTED)) {
                Log::info('ZATCA webhook: skipping downgrade', [
                    'event'            => 'invoice.rejected',
                    'invoice_id'       => $fresh->id,
                    'current_status'   => $fresh->compliance_status,
                    'requested_status' => Invoice::COMPLIANCE_REJECTED,
                ]);

                return response()->json(['success' => true, 'message' => 'Event processed'], 200);
            }

            $errors = $data['errors'] ?? $data['validation_results'] ?? $data;

            $fresh->compliance_status   = Invoice::COMPLIANCE_REJECTED;
            $fresh->compliance_response = $errors;
            $fresh->save();

            Log::info('ZATCA webhook: invoice.rejected processed', [
                'invoice_id' => $fresh->id,
                'event'      => 'invoice.rejected',
            ]);

            return response()->json(['success' => true, 'message' => 'Event processed'], 200);
        });
    }

    private function handleIssued(Invoice $invoice, array $data): JsonResponse
    {
        return DB::transaction(function () use ($invoice, $data): JsonResponse {
            $fresh = $invoice->newQuery()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->find($invoice->id);

            if (!$fresh) {
                return response()->json(['success' => true, 'message' => 'Event processed'], 200);
            }

            $fields = [];

            if (!empty($data['hash'])) {
                $fields['compliance_hash'] = $data['hash'];
            }

            if (!empty($data['qr_code'])) {
                $fields['compliance_qr_code'] = $data['qr_code'];
            }

            if (!empty($fields)) {
                $fresh->fill($fields)->save();
            }

            Log::info('ZATCA webhook: invoice.issued processed', [
                'invoice_id' => $fresh->id,
                'event'      => 'invoice.issued',
            ]);

            return response()->json(['success' => true, 'message' => 'Event processed'], 200);
        });
    }

    private function updateInvoice(
        Invoice $invoice,
        string $newStatus,
        array $data,
        string $event,
        bool $updateHashAndQr
    ): JsonResponse {
        return DB::transaction(function () use ($invoice, $newStatus, $data, $event, $updateHashAndQr): JsonResponse {
            $fresh = $invoice->newQuery()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->find($invoice->id);

            if (!$fresh) {
                return response()->json(['success' => true, 'message' => 'Event processed'], 200);
            }

            if ($this->isDowngrade($fresh->compliance_status, $newStatus)) {
                Log::info('ZATCA webhook: skipping downgrade', [
                    'event'            => $event,
                    'invoice_id'       => $fresh->id,
                    'current_status'   => $fresh->compliance_status,
                    'requested_status' => $newStatus,
                ]);

                return response()->json(['success' => true, 'message' => 'Event processed'], 200);
            }

            $fields = ['compliance_status' => $newStatus];

            if ($updateHashAndQr) {
                if (!empty($data['hash'])) {
                    $fields['compliance_hash'] = $data['hash'];
                }

                if (!empty($data['qr_code'])) {
                    $fields['compliance_qr_code'] = $data['qr_code'];
                }
            }

            $fresh->fill($fields)->save();

            Log::info('ZATCA webhook: event processed', [
                'invoice_id' => $fresh->id,
                'event'      => $event,
                'new_status' => $newStatus,
            ]);

            return response()->json(['success' => true, 'message' => 'Event processed'], 200);
        });
    }

    private function isDowngrade(string $current, string $requested): bool
    {
        $currentPriority   = self::STATUS_PRIORITY[$current]   ?? 0;
        $requestedPriority = self::STATUS_PRIORITY[$requested] ?? 0;

        return $requestedPriority < $currentPriority;
    }

    private function unknownEvent(string $event): JsonResponse
    {
        Log::info('ZATCA webhook: unknown event received, acknowledging to prevent retries', [
            'event' => $event,
        ]);

        return response()->json(['success' => true, 'message' => 'Event processed'], 200);
    }
}
