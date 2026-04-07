<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\EdiMessage;
use App\Models\Core\EdiMessageSegment;
use App\Models\Core\EdiPartner;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EdiService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}
    // -------------------------------------------------------------------------
    // Partner management
    // -------------------------------------------------------------------------

    public function listPartners(array $filters): LengthAwarePaginator
    {
        $query = EdiPartner::orderBy('partner_name');

        if (!empty($filters['partner_type'])) {
            $query->forType($filters['partner_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('partner_name', 'like', "%{$search}%")
                    ->orWhere('partner_code', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function createPartner(array $data): EdiPartner
    {
        return EdiPartner::create($data);
    }

    public function updatePartner(EdiPartner $partner, array $data): EdiPartner
    {
        $partner->update($data);

        return $partner->refresh();
    }

    // -------------------------------------------------------------------------
    // Message management
    // -------------------------------------------------------------------------

    /**
     * Record an inbound raw EDI message and parse its content to JSON.
     */
    public function receiveMessage(int $partnerId, string $messageType, string $rawContent): EdiMessage
    {
        $partner = EdiPartner::findOrFail($partnerId);

        return DB::transaction(function () use ($partner, $messageType, $rawContent): EdiMessage {
            $parsed = $this->parseRawContent($rawContent, $partner->edi_standard);

            $message = EdiMessage::create([
                'organization_id' => $partner->organization_id,
                'edi_partner_id'  => $partner->id,
                'message_type'    => $messageType,
                'direction'       => EdiMessage::DIRECTION_INBOUND,
                'status'          => EdiMessage::STATUS_RECEIVED,
                'raw_content'     => $rawContent,
                'parsed_content'  => $parsed['data'] ?? null,
                'control_number'  => $parsed['control_number'] ?? null,
                'received_at'     => now(),
            ]);

            $this->storeSegments($message, $parsed['segments'] ?? []);

            return $message;
        });
    }

    /**
     * Process a received EDI message: route to the appropriate handler,
     * update status, and populate reference_type / reference_id.
     */
    public function processMessage(EdiMessage $message): EdiMessage
    {
        if (!in_array($message->status, [EdiMessage::STATUS_RECEIVED, EdiMessage::STATUS_FAILED], true)) {
            throw new RuntimeException(
                "Message in status '{$message->status}' cannot be processed."
            );
        }

        $message->update(['status' => EdiMessage::STATUS_PROCESSING]);

        try {
            $result = $this->routeMessageToHandler($message);

            $message->update([
                'status'         => EdiMessage::STATUS_PROCESSED,
                'reference_type' => $result['reference_type'] ?? null,
                'reference_id'   => $result['reference_id'] ?? null,
                'processed_at'   => now(),
                'error_message'  => null,
            ]);
        } catch (\Throwable $e) {
            $message->update([
                'status'        => EdiMessage::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $message->refresh();
    }

    /**
     * Generate and record an outbound EDI message for a trading partner.
     */
    public function sendMessage(int $partnerId, string $messageType, array $data): EdiMessage
    {
        $partner = EdiPartner::findOrFail($partnerId);

        return DB::transaction(function () use ($partner, $messageType, $data): EdiMessage {
            $rawContent = $this->generateRawContent($messageType, $data, $partner->edi_standard);

            return EdiMessage::create([
                'organization_id' => $partner->organization_id,
                'edi_partner_id'  => $partner->id,
                'message_type'    => $messageType,
                'direction'       => EdiMessage::DIRECTION_OUTBOUND,
                'status'          => EdiMessage::STATUS_SENT,
                'raw_content'     => $rawContent,
                'parsed_content'  => $data,
                'reference_type'  => $data['reference_type'] ?? null,
                'reference_id'    => $data['reference_id'] ?? null,
            ]);
        });
    }

    /**
     * Paginate message history for a specific partner.
     */
    public function getMessageHistory(int $partnerId, array $filters): LengthAwarePaginator
    {
        $query = EdiMessage::where('edi_partner_id', $partnerId)
            ->orderByDesc('created_at');

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['message_type'])) {
            $query->where('message_type', $filters['message_type']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Reset a failed or processed message and re-trigger processing.
     */
    public function reprocess(EdiMessage $message): EdiMessage
    {
        $message->update([
            'status'        => EdiMessage::STATUS_RECEIVED,
            'error_message' => null,
            'processed_at'  => null,
        ]);

        return $this->processMessage($message);
    }

    /**
     * Paginate all messages with optional filters.
     */
    public function listMessages(array $filters): LengthAwarePaginator
    {
        $query = EdiMessage::with(['partner:id,partner_code,partner_name'])
            ->orderByDesc('created_at');

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['partner_id'])) {
            $query->where('edi_partner_id', (int) $filters['partner_id']);
        }

        if (!empty($filters['message_type'])) {
            $query->where('message_type', $filters['message_type']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parse raw EDI / XML content into a structured array.
     * This is a stub implementation; a full parser would be injected as a
     * strategy depending on the EDI standard (EDIFACT, X12, UBL, IDoc).
     */
    private function parseRawContent(string $rawContent, string $standard): array
    {
        // Minimal JSON detection — supports UBL/IDoc XML or pre-encoded JSON payloads
        $trimmed = ltrim($rawContent);

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($rawContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    'EDI message content is not valid JSON: ' . json_last_error_msg()
                );
            }

            return [
                'data'           => $decoded,
                'segments'       => [],
                'control_number' => null,
            ];
        }

        // For EDIFACT / X12 — parse top-level segments by splitting on single-quote
        $segments = [];
        $lines    = explode("'", $rawContent);
        $seq      = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts     = explode('+', $line);
            $segmentId = strtoupper($parts[0] ?? '');

            if (strlen($segmentId) < 2 || strlen($segmentId) > 10) {
                continue;
            }

            $segments[] = [
                'segment_id'       => $segmentId,
                'segment_sequence' => ++$seq,
                'segment_data'     => $parts,
            ];
        }

        return [
            'data'           => ['raw_segments' => count($segments)],
            'segments'       => $segments,
            'control_number' => null,
        ];
    }

    /**
     * Persist parsed segments for a message.
     */
    private function storeSegments(EdiMessage $message, array $segments): void
    {
        foreach ($segments as $seg) {
            EdiMessageSegment::create([
                'organization_id'  => $message->organization_id,
                'edi_message_id'   => $message->id,
                'segment_id'       => $seg['segment_id'],
                'segment_sequence' => $seg['segment_sequence'],
                'segment_data'     => $seg['segment_data'],
            ]);
        }
    }

    /**
     * Route a parsed inbound message to the correct domain handler.
     * Returns an array with reference_type and reference_id if a document was created.
     *
     * @return array{reference_type: string|null, reference_id: int|null}
     */
    private function routeMessageToHandler(EdiMessage $message): array
    {
        return match ($message->message_type) {
            'ORDERS', 'IDOC_ORDERS01' => $this->handleInboundOrder($message),
            'INVOIC', 'IDOC_INVOIC01' => $this->handleInboundInvoice($message),
            default                   => ['reference_type' => null, 'reference_id' => null],
        };
    }

    /**
     * Handle an inbound purchase-order EDI message.
     * Extracts supplier, order date, and lines from parsed_content and creates a draft PurchaseOrder.
     *
     * @return array{reference_type: string, reference_id: int}
     */
    private function handleInboundOrder(EdiMessage $message): array
    {
        $content = $message->parsed_content ?? [];

        if (empty($content['supplier_id'])) {
            throw new RuntimeException('EDI ORDERS message is missing required field: supplier_id.');
        }

        if (empty($content['lines']) || !is_array($content['lines'])) {
            throw new RuntimeException('EDI ORDERS message is missing required field: lines.');
        }

        $orgId = $message->organization_id;

        $order = DB::transaction(function () use ($content, $orgId): PurchaseOrder {
            $supplier    = Contact::where('organization_id', $orgId)
                ->findOrFail((int) $content['supplier_id']);

            $orderNumber = $this->numberGenerator->generate('PO', null, $orgId);

            $order = PurchaseOrder::create([
                'organization_id'        => $orgId,
                'order_number'           => $orderNumber,
                'supplier_id'            => $supplier->id,
                'supplier_name'          => $supplier->getDisplayName(),
                'order_date'             => $content['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $content['expected_delivery_date'] ?? null,
                'currency_code'          => $content['currency_code'] ?? 'SAR',
                'exchange_rate'          => max(0.0001, (float) ($content['exchange_rate'] ?? 1)),
                'status'                 => PurchaseOrder::STATUS_DRAFT,
                'notes'                  => $content['notes'] ?? null,
                'subtotal'               => 0,
                'tax_amount'             => 0,
                'total'                  => 0,
            ]);

            $subtotal = '0';
            $taxTotal = '0';

            foreach ($content['lines'] as $lineData) {
                if (empty($lineData['description']) && empty($lineData['product_id'])) {
                    throw new RuntimeException('Each EDI order line must have a description or product_id.');
                }

                $qty       = (string) max(0, (float) ($lineData['quantity'] ?? 1));
                $price     = (string) max(0, (float) ($lineData['unit_price'] ?? 0));
                $taxAmt    = (string) max(0, (float) ($lineData['tax_amount'] ?? 0));
                $lineTotal = bcmul($qty, $price, 4);

                $order->lines()->create([
                    'product_id'  => $lineData['product_id'] ?? null,
                    'description' => $lineData['description'] ?? '',
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'tax_rate'    => min(100, max(0, (float) ($lineData['tax_rate'] ?? 0))),
                    'tax_amount'  => $taxAmt,
                    'subtotal'    => $lineTotal,
                    'total'       => bcadd($lineTotal, $taxAmt, 4),
                ]);

                $subtotal = bcadd($subtotal, $lineTotal, 4);
                $taxTotal = bcadd($taxTotal, $taxAmt, 4);
            }

            $order->update([
                'subtotal'   => $subtotal,
                'tax_amount' => $taxTotal,
                'total'      => bcadd($subtotal, $taxTotal, 4),
            ]);

            return $order;
        });

        return ['reference_type' => 'purchase_order', 'reference_id' => $order->id];
    }

    /**
     * Handle an inbound invoice EDI message.
     * Extracts supplier, bill date, and lines from parsed_content and creates a draft Bill.
     *
     * @return array{reference_type: string, reference_id: int}
     */
    private function handleInboundInvoice(EdiMessage $message): array
    {
        $content = $message->parsed_content ?? [];

        if (empty($content['supplier_id'])) {
            throw new RuntimeException('EDI INVOIC message is missing required field: supplier_id.');
        }

        if (empty($content['lines']) || !is_array($content['lines'])) {
            throw new RuntimeException('EDI INVOIC message is missing required field: lines.');
        }

        $orgId = $message->organization_id;

        $bill = DB::transaction(function () use ($content, $orgId): Bill {
            $supplier   = Contact::where('organization_id', $orgId)
                ->findOrFail((int) $content['supplier_id']);

            $billNumber = $this->numberGenerator->generate('BILL', null, $orgId);

            $bill = Bill::create([
                'organization_id'         => $orgId,
                'bill_number'             => $billNumber,
                'supplier_invoice_number' => $content['supplier_invoice_number'] ?? null,
                'supplier_id'             => $supplier->id,
                'supplier_name'           => $supplier->getDisplayName(),
                'bill_date'               => $content['bill_date'] ?? now()->toDateString(),
                'due_date'                => $content['due_date'] ?? null,
                'currency_code'           => $content['currency_code'] ?? 'SAR',
                'exchange_rate'           => max(0.0001, (float) ($content['exchange_rate'] ?? 1)),
                'status'                  => Bill::STATUS_DRAFT,
                'notes'                   => $content['notes'] ?? null,
                'subtotal'                => 0,
                'tax_amount'              => 0,
                'total'                   => 0,
            ]);

            $subtotal   = '0';
            $taxTotal   = '0';

            foreach ($content['lines'] as $lineData) {
                if (empty($lineData['description']) && empty($lineData['product_id'])) {
                    throw new RuntimeException('Each EDI invoice line must have a description or product_id.');
                }

                $qty       = (string) max(0, (float) ($lineData['quantity'] ?? 1));
                $price     = (string) max(0, (float) ($lineData['unit_price'] ?? 0));
                $taxAmt    = (string) max(0, (float) ($lineData['tax_amount'] ?? 0));
                $lineTotal = bcmul($qty, $price, 4);

                $bill->lines()->create([
                    'product_id'  => $lineData['product_id'] ?? null,
                    'description' => $lineData['description'] ?? '',
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'tax_rate'    => min(100, max(0, (float) ($lineData['tax_rate'] ?? 0))),
                    'tax_amount'  => $taxAmt,
                    'subtotal'    => $lineTotal,
                    'total'       => bcadd($lineTotal, $taxAmt, 4),
                ]);

                $subtotal = bcadd($subtotal, $lineTotal, 4);
                $taxTotal = bcadd($taxTotal, $taxAmt, 4);
            }

            $bill->update([
                'subtotal'   => $subtotal,
                'tax_amount' => $taxTotal,
                'total'      => bcadd($subtotal, $taxTotal, 4),
            ]);

            return $bill;
        });

        return ['reference_type' => 'bill', 'reference_id' => $bill->id];
    }

    /**
     * Generate a minimal but structurally valid EDI message string.
     * Produces X12 or EDIFACT envelope syntax depending on $standard.
     * For production use with a specific trading partner, replace this
     * with a dedicated EDI library (e.g., php-edifact/edifact).
     */
    private function generateRawContent(string $messageType, array $data, string $standard): string
    {
        $controlNumber = str_pad((string) (time() % 1000000000), 9, '0', STR_PAD_LEFT);
        $date          = now()->format('ymd');
        $time          = now()->format('Hi');

        $senderId   = strtoupper(substr(config('app.name', 'ERPAPP'), 0, 15));
        $receiverId = strtoupper((string) ($data['partner_code'] ?? 'PARTNER'));

        if (strtoupper($standard) === 'X12') {
            // Minimal X12 envelope: ISA / GS / ST / <payload segment> / SE / GE / IEA
            $isa = implode('*', [
                'ISA', '00', str_pad('', 10), '00', str_pad('', 10),
                'ZZ', str_pad($senderId, 15), 'ZZ', str_pad($receiverId, 15),
                $date, $time, '^', '00501', $controlNumber, '0', 'P', '>',
            ]);

            $gs = implode('*', ['GS', $messageType, $senderId, $receiverId, $date, $time, '1', 'X', '005010']);

            $st = implode('*', ['ST', $messageType, '0001']);

            // Encode payload as a BEG (beginning) segment with key=value pairs
            $payload = implode('*', array_merge(
                ['BEG'],
                array_map(
                    static fn($k, $v): string => "{$k}:" . (is_scalar($v) ? (string) $v : json_encode($v)),
                    array_keys($data),
                    array_values($data),
                )
            ));

            $se  = implode('*', ['SE', '3', '0001']);
            $ge  = implode('*', ['GE', '1', '1']);
            $iea = implode('*', ['IEA', '1', $controlNumber]);

            return implode('~' . "\n", [$isa, $gs, $st, $payload, $se, $ge, $iea]) . '~';
        }

        // Default: EDIFACT envelope — UNB / UNH / <message type segment> / UNT / UNZ
        $unb = "UNB+UNOA:3+{$senderId}:{$receiverId}+{$date}:{$time}+{$controlNumber}";

        $unh = "UNH+1+{$messageType}:D:96A:UN";

        // Encode payload fields as DTM/NAD free-form segments
        $segments = [];
        foreach ($data as $key => $value) {
            $encoded    = is_scalar($value) ? (string) $value : json_encode($value);
            $segments[] = "DTM+{$key}:{$encoded}";
        }

        $unt = "UNT+" . (count($segments) + 2) . "+1";
        $unz = "UNZ+1+{$controlNumber}";

        return implode("'" . "\n", array_merge([$unb, $unh], $segments, [$unt, $unz])) . "'";
    }
}
