<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\TimeEvaluationResult;
use App\Models\HR\TimeSheet;
use App\Models\HR\TimeSheetEntry;
use App\Models\HR\TimeWageType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TimeEvaluationService
{
    private const STANDARD_DAILY_HOURS = 8.0;
    private const NIGHT_SHIFT_START    = 22; // 22:00
    private const NIGHT_SHIFT_END      = 6;  // 06:00

    // ---------------------------------------------------------------
    // Time Sheet CRUD
    // ---------------------------------------------------------------

    public function createTimeSheet(array $data): TimeSheet
    {
        return DB::transaction(function () use ($data): TimeSheet {
            $existing = TimeSheet::where('employee_id', $data['employee_id'])
                ->where('period_start', $data['period_start'])
                ->where('period_end', $data['period_end'])
                ->first();

            if ($existing !== null) {
                throw new \InvalidArgumentException(
                    'A timesheet already exists for this employee and period.'
                );
            }

            return TimeSheet::create([
                'organization_id' => $data['organization_id'],
                'employee_id'     => $data['employee_id'],
                'period_start'    => $data['period_start'],
                'period_end'      => $data['period_end'],
                'status'          => TimeSheet::STATUS_DRAFT,
                'created_by'      => $data['created_by'],
            ]);
        });
    }

    public function addEntry(TimeSheet $timeSheet, array $data): TimeSheetEntry
    {
        if (!$timeSheet->isDraft()) {
            throw new \InvalidArgumentException(
                'Entries can only be added to draft timesheets.'
            );
        }

        $entryDate = new \DateTime($data['entry_date']);
        $periodStart = new \DateTime($timeSheet->period_start->format('Y-m-d'));
        $periodEnd   = new \DateTime($timeSheet->period_end->format('Y-m-d'));

        if ($entryDate < $periodStart || $entryDate > $periodEnd) {
            throw new \InvalidArgumentException(
                'Entry date must fall within the timesheet period.'
            );
        }

        return DB::transaction(function () use ($timeSheet, $data): TimeSheetEntry {
            $entry = TimeSheetEntry::create([
                'time_sheet_id'  => $timeSheet->id,
                'entry_date'     => $data['entry_date'],
                'start_time'     => $data['start_time'] ?? null,
                'end_time'       => $data['end_time'] ?? null,
                'hours'          => $data['hours'],
                'entry_type'     => $data['entry_type'] ?? TimeSheetEntry::TYPE_REGULAR,
                'wage_type_id'   => $data['wage_type_id'] ?? null,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'project_id'     => $data['project_id'] ?? null,
                'wbs_element_id' => $data['wbs_element_id'] ?? null,
                'work_order_id'  => $data['work_order_id'] ?? null,
                'activity_code'  => $data['activity_code'] ?? null,
                'notes'          => $data['notes'] ?? null,
            ]);

            $this->recalculateTotals($timeSheet);

            return $entry;
        });
    }

    // ---------------------------------------------------------------
    // Workflow transitions
    // ---------------------------------------------------------------

    public function submit(TimeSheet $timeSheet): void
    {
        if (!$timeSheet->isDraft()) {
            throw new \InvalidArgumentException('Only draft timesheets can be submitted.');
        }

        if ($timeSheet->entries()->count() === 0) {
            throw new \InvalidArgumentException('Cannot submit an empty timesheet.');
        }

        $timeSheet->update(['status' => TimeSheet::STATUS_SUBMITTED]);
    }

    public function approve(TimeSheet $timeSheet): void
    {
        if (!$timeSheet->isSubmitted()) {
            throw new \InvalidArgumentException('Only submitted timesheets can be approved.');
        }

        DB::transaction(function () use ($timeSheet): void {
            $timeSheet->update([
                'status'      => TimeSheet::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $this->evaluate($timeSheet);
        });
    }

    public function reject(TimeSheet $timeSheet, string $reason): void
    {
        if (!$timeSheet->isSubmitted()) {
            throw new \InvalidArgumentException('Only submitted timesheets can be rejected.');
        }

        $timeSheet->update([
            'status' => TimeSheet::STATUS_REJECTED,
        ]);

        Log::info('TimeSheet rejected', [
            'time_sheet_id' => $timeSheet->id,
            'reason'        => $reason,
            'rejected_by'   => auth()->id(),
        ]);
    }

    // ---------------------------------------------------------------
    // Evaluation
    // ---------------------------------------------------------------

    /**
     * Evaluate a timesheet: classify hours into wage types and create
     * TimeEvaluationResult records.
     *
     * @return array{wage_type_breakdown: array, total_hours: float, total_amount: float}
     */
    public function evaluate(TimeSheet $timeSheet): array
    {
        if ($timeSheet->status !== TimeSheet::STATUS_APPROVED
            && $timeSheet->status !== TimeSheet::STATUS_DRAFT
        ) {
            throw new \InvalidArgumentException(
                'Timesheet must be approved before evaluation.'
            );
        }

        return DB::transaction(function () use ($timeSheet): array {
            // Remove existing results to allow re-evaluation
            $timeSheet->evaluationResults()->delete();

            $employee = $timeSheet->employee()->firstOrFail();
            $orgId    = $timeSheet->organization_id;

            $standardDailyHours = $this->getStandardDailyHours($employee);
            $wageTypeMap        = $this->loadWageTypeMap($orgId);
            $breakdown          = [];

            foreach ($timeSheet->entries as $entry) {
                $dayType = $this->classifyDayType($entry->entry_date->format('Y-m-d'), $orgId);

                $results = $this->classifyEntry($entry, $standardDailyHours, $dayType, $wageTypeMap);

                foreach ($results as $wageTypeCode => $hours) {
                    $wageType = $wageTypeMap[$wageTypeCode] ?? null;
                    if ($wageType === null || $hours <= 0) {
                        continue;
                    }

                    TimeEvaluationResult::create([
                        'time_sheet_id'  => $timeSheet->id,
                        'employee_id'    => $employee->id,
                        'evaluation_date' => $entry->entry_date->format('Y-m-d'),
                        'wage_type_id'   => $wageType->id,
                        'hours'          => $hours,
                        'amount'         => 0, // Amount calculated during payroll transfer
                        'currency_code'  => $employee->currency_code ?? 'SAR',
                    ]);

                    $breakdown[$wageTypeCode] = ($breakdown[$wageTypeCode] ?? 0) + $hours;
                }
            }

            $totalHours  = array_sum($breakdown);
            $totalAmount = $timeSheet->evaluationResults()->sum('amount');

            return [
                'wage_type_breakdown' => $breakdown,
                'total_hours'         => $totalHours,
                'total_amount'        => (float) $totalAmount,
            ];
        });
    }

    // ---------------------------------------------------------------
    // Payroll transfer
    // ---------------------------------------------------------------

    /**
     * Mark all evaluation results as transferred and return payroll input.
     *
     * @return array{employee_id: int, period_start: string, period_end: string, wage_types: array}
     */
    public function transferToPayroll(TimeSheet $timeSheet): array
    {
        if (!$timeSheet->isApproved()) {
            throw new \InvalidArgumentException(
                'Only approved timesheets can be transferred to payroll.'
            );
        }

        return DB::transaction(function () use ($timeSheet): array {
            $timeSheet->evaluationResults()
                ->where('transferred_to_payroll', false)
                ->update(['transferred_to_payroll' => true]);

            $timeSheet->update(['status' => TimeSheet::STATUS_TRANSFERRED_TO_PAYROLL]);

            $wageTypes = $timeSheet->evaluationResults()
                ->with('wageType')
                ->get()
                ->groupBy('wage_type_id')
                ->map(fn ($items) => [
                    'wage_type_code' => $items->first()->wageType->code,
                    'wage_type_name' => $items->first()->wageType->name,
                    'total_hours'    => $items->sum('hours'),
                    'total_amount'   => $items->sum('amount'),
                ])
                ->values()
                ->toArray();

            return [
                'employee_id'  => $timeSheet->employee_id,
                'period_start' => $timeSheet->period_start->format('Y-m-d'),
                'period_end'   => $timeSheet->period_end->format('Y-m-d'),
                'wage_types'   => $wageTypes,
            ];
        });
    }

    // ---------------------------------------------------------------
    // Cost allocation
    // ---------------------------------------------------------------

    /**
     * Group entries by cost center / project / WBS and return hours per object.
     *
     * @return array<int, array{cost_center_id: int|null, project_id: int|null, wbs_element_id: int|null, total_hours: float, total_amount: float}>
     */
    public function generateCostAllocation(TimeSheet $timeSheet): array
    {
        $entries = $timeSheet->entries()
            ->with('costCenter')
            ->get();

        $grouped = [];

        foreach ($entries as $entry) {
            $key = sprintf(
                'cc:%s|proj:%s|wbs:%s',
                $entry->cost_center_id ?? 'null',
                $entry->project_id ?? 'null',
                $entry->wbs_element_id ?? 'null'
            );

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'cost_center_id' => $entry->cost_center_id,
                    'project_id'     => $entry->project_id,
                    'wbs_element_id' => $entry->wbs_element_id,
                    'total_hours'    => 0.0,
                    'total_amount'   => 0.0,
                ];
            }

            $grouped[$key]['total_hours'] += (float) $entry->hours;
        }

        // Attach amounts from evaluation results proportionally
        $totalHours = array_sum(array_column($grouped, 'total_hours'));
        $totalAmount = (float) $timeSheet->evaluationResults()->sum('amount');

        if ($totalHours > 0 && $totalAmount > 0) {
            foreach ($grouped as &$group) {
                $group['total_amount'] = round(
                    ($group['total_hours'] / $totalHours) * $totalAmount,
                    4
                );
            }
            unset($group);
        }

        return array_values($grouped);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function recalculateTotals(TimeSheet $timeSheet): void
    {
        $totals = $timeSheet->entries()
            ->selectRaw('entry_type, SUM(hours) as total')
            ->groupBy('entry_type')
            ->pluck('total', 'entry_type');

        $timeSheet->update([
            'total_regular_hours'  => $totals[TimeSheetEntry::TYPE_REGULAR] ?? 0,
            'total_overtime_hours' => $totals[TimeSheetEntry::TYPE_OVERTIME] ?? 0,
            'total_absence_hours'  => $totals[TimeSheetEntry::TYPE_ABSENCE] ?? 0,
        ]);
    }

    private function getStandardDailyHours(Employee $employee): float
    {
        // Use work_schedule from employee if defined, fallback to 8h/day
        if (!empty($employee->work_schedule)) {
            $schedule = is_array($employee->work_schedule)
                ? $employee->work_schedule
                : [];
            if (isset($schedule['daily_hours'])) {
                return (float) $schedule['daily_hours'];
            }
        }

        return self::STANDARD_DAILY_HOURS;
    }

    /**
     * @return array<string, TimeWageType>
     */
    private function loadWageTypeMap(int $orgId): array
    {
        return TimeWageType::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get()
            ->keyBy('code')
            ->toArray();
    }

    private function classifyDayType(string $date, int $orgId): string
    {
        $dayOfWeek = (int) date('N', strtotime($date)); // 1=Mon, 7=Sun

        if ($dayOfWeek >= 6) { // Saturday or Sunday
            return 'weekend';
        }

        return 'weekday';
    }

    /**
     * Classify an entry into hours per wage-type code.
     *
     * @param  array<string, TimeWageType>  $wageTypeMap
     * @return array<string, float>
     */
    private function classifyEntry(
        TimeSheetEntry $entry,
        float $standardDailyHours,
        string $dayType,
        array $wageTypeMap
    ): array {
        $hours  = (float) $entry->hours;
        $result = [];

        if ($entry->entry_type === TimeSheetEntry::TYPE_ABSENCE) {
            $result['ABSENT'] = $hours;
            return $result;
        }

        if ($dayType === 'weekend') {
            $result['WE'] = $hours;
            return $result;
        }

        // Weekday: split regular / overtime
        $regularHours  = min($hours, $standardDailyHours);
        $overtimeHours = max(0.0, $hours - $standardDailyHours);

        if ($regularHours > 0) {
            // Check night differential
            if ($entry->isNightShift()) {
                $result['NT'] = $regularHours;
            } else {
                $result['REG'] = $regularHours;
            }
        }

        if ($overtimeHours > 0) {
            // Default 1.5x overtime code; prefer wage_type from entry if set
            $overtimeCode = 'OT15';
            if ($entry->wageType !== null) {
                $overtimeCode = $entry->wageType->code;
            }
            $result[$overtimeCode] = $overtimeHours;
        }

        return $result;
    }
}
