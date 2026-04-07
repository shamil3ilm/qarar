<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\AccrualDeferral;
use App\Models\Core\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccrualDeferralService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Paginate accrual/deferral entries with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = AccrualDeferral::with(['debitAccount:id,code,name', 'creditAccount:id,code,name', 'createdBy:id,name'])
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('end_date', '<=', $filters['end_date']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Create a new accrual or deferral entry.
     * Calculates per_period_amount from total_amount / periods_total.
     */
    public function store(array $data): AccrualDeferral
    {
        return DB::transaction(function () use ($data) {
            $periodsTotal = (int) $data['periods_total'];

            if ($periodsTotal <= 0) {
                throw new InvalidArgumentException('periods_total must be greater than zero.');
            }

            $totalAmount = (float) $data['total_amount'];
            $perPeriodAmount = round($totalAmount / $periodsTotal, 4);

            return AccrualDeferral::create([
                'organization_id'   => $data['organization_id'],
                'reference'         => $data['reference'],
                'type'              => $data['type'],
                'debit_account_id'  => $data['debit_account_id'],
                'credit_account_id' => $data['credit_account_id'],
                'total_amount'      => $totalAmount,
                'per_period_amount' => $perPeriodAmount,
                'currency_code'     => $data['currency_code'] ?? 'SAR',
                'start_date'        => $data['start_date'],
                'end_date'          => $data['end_date'],
                'periods_total'     => $periodsTotal,
                'periods_posted'    => 0,
                'status'            => AccrualDeferral::STATUS_ACTIVE,
                'description'       => $data['description'] ?? null,
                'created_by'        => $data['created_by'],
            ]);
        });
    }

    /**
     * Post the accrual/deferral for a given period number.
     * Creates a journal entry and increments periods_posted.
     */
    public function postPeriod(AccrualDeferral $entry, int $period): AccrualDeferral
    {
        return DB::transaction(function () use ($entry, $period) {
            if ($entry->status !== AccrualDeferral::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Only active accrual/deferral entries can be posted.');
            }

            if ($period < 1 || $period > $entry->periods_total) {
                throw new InvalidArgumentException(
                    "Period {$period} is out of range (1–{$entry->periods_total})."
                );
            }

            if ($period <= $entry->periods_posted) {
                throw new InvalidArgumentException("Period {$period} has already been posted.");
            }

            $description = sprintf(
                '%s — Period %d/%d (%s)',
                strtoupper($entry->type),
                $period,
                $entry->periods_total,
                $entry->reference
            );

            $this->journalService->createEntry([
                'organization_id' => $entry->organization_id,
                'entry_date'      => now()->toDateString(),
                'reference'       => $entry->reference . '-P' . $period,
                'description'     => $description,
                'currency_code'   => $entry->currency_code,
                'status'          => 'posted',
                'source_type'     => AccrualDeferral::class,
                'source_id'       => $entry->id,
            ], [
                [
                    'account_id'  => $entry->debit_account_id,
                    'debit'       => $entry->per_period_amount,
                    'credit'      => 0,
                    'description' => $description,
                ],
                [
                    'account_id'  => $entry->credit_account_id,
                    'debit'       => 0,
                    'credit'      => $entry->per_period_amount,
                    'description' => $description,
                ],
            ]);

            $newPeriodsPosted = $entry->periods_posted + 1;
            $newStatus = ($newPeriodsPosted >= $entry->periods_total)
                ? AccrualDeferral::STATUS_COMPLETED
                : AccrualDeferral::STATUS_ACTIVE;

            $entry->update([
                'periods_posted' => $newPeriodsPosted,
                'status'         => $newStatus,
            ]);

            return $entry->fresh(['debitAccount', 'creditAccount']);
        });
    }

    /**
     * Post all active accruals for the given organization, year, and month.
     * Each entry's next unposted period is determined automatically.
     */
    public function runMonthlyAccruals(Organization $org, int $year, int $month): array
    {
        $entries = AccrualDeferral::where('organization_id', $org->id)
            ->active()
            ->get();

        $results = ['posted' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($entries as $entry) {
            $nextPeriod = $entry->periods_posted + 1;

            if ($nextPeriod > $entry->periods_total) {
                $results['skipped']++;
                continue;
            }

            try {
                $this->postPeriod($entry, $nextPeriod);
                $results['posted']++;
            } catch (\Throwable $e) {
                $results['errors'][] = [
                    'entry_id'  => $entry->id,
                    'reference' => $entry->reference,
                    'error'     => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
