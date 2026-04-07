<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\TravelExpenseReport;
use App\Models\HR\TravelExpenseReportLine;
use App\Models\HR\TravelExpenseType;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

class TravelExpenseReportService
{
    public function __construct(
        private JournalService $journalService,
        private NumberGeneratorService $numberGenerator,
    ) {}

    public function submitRequest(int $organizationId, array $data, int $userId): \App\Models\HR\TravelRequest
    {
        $travelRequest = \App\Models\HR\TravelRequest::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('uuid', $data['uuid'] ?? '')
            ->firstOrFail();

        if ($travelRequest->status !== \App\Models\HR\TravelRequest::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft requests can be submitted.');
        }

        $requestNumber = $this->numberGenerator->generate('TR', '{prefix}-{year}-{number}', $organizationId);

        $travelRequest->update([
            'status'         => \App\Models\HR\TravelRequest::STATUS_SUBMITTED,
            'request_number' => $travelRequest->request_number ?? $requestNumber,
        ]);

        return $travelRequest->refresh();
    }

    public function approveRequest(int $organizationId, string $uuid, int $approverId): \App\Models\HR\TravelRequest
    {
        $travelRequest = \App\Models\HR\TravelRequest::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($travelRequest->status !== \App\Models\HR\TravelRequest::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException('Only submitted requests can be approved.');
        }

        $travelRequest->update([
            'status'      => \App\Models\HR\TravelRequest::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $travelRequest->refresh();
    }

    public function submitExpenseReport(int $organizationId, array $data, int $userId): TravelExpenseReport
    {
        return DB::transaction(function () use ($organizationId, $data, $userId) {
            $reportNumber = $this->numberGenerator->generate('ER', '{prefix}-{year}-{number}', $organizationId);

            $report = TravelExpenseReport::create([
                'organization_id'   => $organizationId,
                'report_number'     => $reportNumber,
                'travel_request_id' => $data['travel_request_id'] ?? null,
                'employee_id'       => $data['employee_id'],
                'report_date'       => $data['report_date'] ?? now()->toDateString(),
                'currency_code'     => $data['currency_code'] ?? 'SAR',
                'status'            => TravelExpenseReport::STATUS_DRAFT,
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $userId,
                'total_amount'      => '0.0000',
            ]);

            $totalAmount = '0.0000';

            foreach ($data['lines'] ?? [] as $lineData) {
                TravelExpenseReportLine::create([
                    'expense_report_id' => $report->id,
                    'expense_type_id'   => $lineData['expense_type_id'],
                    'expense_date'      => $lineData['expense_date'],
                    'description'       => $lineData['description'],
                    'amount'            => $lineData['amount'],
                    'currency_code'     => $lineData['currency_code'] ?? $data['currency_code'] ?? 'SAR',
                    'amount_in_local'   => $lineData['amount_in_local'] ?? $lineData['amount'],
                    'receipt_attached'  => $lineData['receipt_attached'] ?? false,
                    'receipt_path'      => $lineData['receipt_path'] ?? null,
                ]);

                $totalAmount = bcadd($totalAmount, (string) $lineData['amount'], 4);
            }

            $report->update(['total_amount' => $totalAmount]);

            return $report->load('lines.expenseType');
        });
    }

    public function approveExpenseReport(int $organizationId, string $uuid, int $approverId): TravelExpenseReport
    {
        $report = TravelExpenseReport::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($report->status !== TravelExpenseReport::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException('Only submitted expense reports can be approved.');
        }

        $report->update([
            'status'      => TravelExpenseReport::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $report->refresh();
    }

    public function postExpenseReport(int $organizationId, string $uuid, int $postedBy): TravelExpenseReport
    {
        return DB::transaction(function () use ($organizationId, $uuid, $postedBy) {
            $report = TravelExpenseReport::withoutGlobalScope('organization')
                ->with('lines.expenseType')
                ->where('organization_id', $organizationId)
                ->where('uuid', $uuid)
                ->firstOrFail();

            if ($report->status !== TravelExpenseReport::STATUS_APPROVED) {
                throw new \InvalidArgumentException('Only approved expense reports can be posted.');
            }

            $journalLines = [];

            foreach ($report->lines as $line) {
                $glAccountCode = $line->expenseType?->gl_account_code ?? '650000';
                $localAmount   = $line->amount_in_local ?? $line->amount;

                $journalLines[] = [
                    'account_code' => $glAccountCode,
                    'debit'        => (string) $localAmount,
                    'credit'       => '0',
                    'description'  => $line->description,
                ];
            }

            $journalLines[] = [
                'account_code' => '210000',
                'debit'        => '0',
                'credit'       => (string) $report->total_amount,
                'description'  => 'Travel expense accrual — report ' . $report->report_number,
            ];

            $entryData = [
                'organization_id' => $organizationId,
                'entry_date'      => now()->toDateString(),
                'reference'       => $report->report_number,
                'description'     => 'Travel Expense Report ' . $report->report_number,
                'document_type'   => 'SA',
                'created_by'      => $postedBy,
            ];

            $journalEntry = $this->journalService->create($entryData, $journalLines);

            $report->update([
                'status'           => TravelExpenseReport::STATUS_POSTED,
                'journal_entry_id' => (string) $journalEntry->id,
            ]);

            return $report->refresh();
        });
    }
}
