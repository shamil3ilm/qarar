<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

class GrIrClearingService
{
    public function __construct(private JournalService $journalService) {}

    public function getOpenItems(int $organizationId, ?string $asOfDate = null): array
    {
        $cutoff = $asOfDate ? \Carbon\Carbon::parse($asOfDate) : now();

        $lines = DB::table('purchase_order_lines as pol')
            ->join('purchase_orders as po', 'pol.purchase_order_id', '=', 'po.id')
            ->where('po.organization_id', $organizationId)
            ->where('po.created_at', '<=', $cutoff)
            ->whereRaw('COALESCE(pol.received_qty, 0) != COALESCE(pol.invoiced_qty, 0)')
            ->whereIn('po.status', ['approved', 'partial', 'received'])
            ->select([
                'pol.id',
                'po.id as purchase_order_id',
                'po.po_number',
                'pol.product_id',
                'pol.description',
                DB::raw('COALESCE(pol.received_qty, 0) as received_qty'),
                DB::raw('COALESCE(pol.invoiced_qty, 0) as invoiced_qty'),
                DB::raw('COALESCE(pol.received_qty, 0) - COALESCE(pol.invoiced_qty, 0) as variance_qty'),
                'pol.unit_price',
                DB::raw('(COALESCE(pol.received_qty, 0) - COALESCE(pol.invoiced_qty, 0)) * pol.unit_price as variance_amount'),
            ])
            ->get();

        $totalVariance = '0.0000';
        foreach ($lines as $line) {
            $totalVariance = bcadd($totalVariance, (string) abs((float) $line->variance_amount), 4);
        }

        return [
            'as_of_date' => $cutoff->toDateString(),
            'items'      => $lines,
            'summary'    => [
                'total_items'            => count($lines),
                'total_variance_amount'  => $totalVariance,
            ],
        ];
    }

    public function clearVariance(int $organizationId, int $poLineId, array $data, int $userId): array
    {
        return DB::transaction(function () use ($organizationId, $poLineId, $data, $userId) {
            $line = DB::table('purchase_order_lines as pol')
                ->join('purchase_orders as po', 'pol.purchase_order_id', '=', 'po.id')
                ->where('po.organization_id', $organizationId)
                ->where('pol.id', $poLineId)
                ->select(['pol.*', 'po.po_number', 'po.supplier_id'])
                ->first();

            if (!$line) {
                throw new \RuntimeException('PO line not found');
            }

            $receivedQty   = (string) ($line->received_qty ?? '0');
            $invoicedQty   = (string) ($line->invoiced_qty ?? '0');
            $varianceQty   = bcsub($receivedQty, $invoicedQty, 4);
            $varianceAmount = bcmul($varianceQty, (string) $line->unit_price, 4);

            $grirAccountCode   = $data['grir_account_code'] ?? '219000';
            $offsetAccountCode = $data['offset_account_code'] ?? '899999';
            $isPositive        = bccomp($varianceAmount, '0', 4) > 0;
            $absAmount         = ltrim($varianceAmount, '-');

            $journalLines = [
                [
                    'account_code' => $grirAccountCode,
                    'debit'        => $isPositive ? $absAmount : '0',
                    'credit'       => $isPositive ? '0' : $absAmount,
                    'description'  => 'GR/IR Clearing',
                ],
                [
                    'account_code' => $offsetAccountCode,
                    'debit'        => $isPositive ? '0' : $absAmount,
                    'credit'       => $isPositive ? $absAmount : '0',
                    'description'  => 'GR/IR Price Difference',
                ],
            ];

            $entryData = [
                'organization_id' => $organizationId,
                'entry_date'      => $data['clearing_date'] ?? now()->toDateString(),
                'reference'       => 'MR11-' . $poLineId,
                'description'     => 'GR/IR Clearing for PO ' . $line->po_number,
                'document_type'   => 'WB',
                'created_by'      => $userId,
            ];

            $journalEntry = $this->journalService->create($entryData, $journalLines);

            DB::table('purchase_order_lines')
                ->where('id', $poLineId)
                ->update(['invoiced_qty' => $receivedQty]);

            return [
                'po_line_id'       => $poLineId,
                'variance_qty'     => $varianceQty,
                'variance_amount'  => $varianceAmount,
                'journal_entry_id' => $journalEntry->id,
                'cleared_at'       => now()->toIso8601String(),
            ];
        });
    }

    public function getReport(int $organizationId, ?string $asOfDate = null): array
    {
        $openItems = $this->getOpenItems($organizationId, $asOfDate);

        $byPo = [];
        foreach ($openItems['items'] as $item) {
            $poNumber = $item->po_number;
            if (!isset($byPo[$poNumber])) {
                $byPo[$poNumber] = [
                    'po_number'  => $poNumber,
                    'po_id'      => $item->purchase_order_id,
                    'lines'      => [],
                    'total_variance' => '0.0000',
                ];
            }
            $byPo[$poNumber]['lines'][]        = $item;
            $byPo[$poNumber]['total_variance'] = bcadd(
                $byPo[$poNumber]['total_variance'],
                (string) abs((float) $item->variance_amount),
                4
            );
        }

        return [
            'as_of_date'   => $openItems['as_of_date'],
            'summary'      => $openItems['summary'],
            'by_po'        => array_values($byPo),
        ];
    }
}
