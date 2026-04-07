<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\OffCyclePayrollItem;
use App\Models\HR\OffCyclePayrollRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OffCyclePayrollService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = OffCyclePayrollRun::query()
            ->with(['processor'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['run_type']), fn($q) => $q->where('run_type', $filters['run_type']))
            ->when(isset($filters['run_date_from']), fn($q) => $q->whereDate('run_date', '>=', $filters['run_date_from']))
            ->when(isset($filters['run_date_to']), fn($q) => $q->whereDate('run_date', '<=', $filters['run_date_to']))
            ->orderBy('run_date', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): OffCyclePayrollRun
    {
        return OffCyclePayrollRun::create([
            'run_type'  => $data['run_type'] ?? OffCyclePayrollRun::RUN_TYPE_BONUS,
            'run_name'  => $data['run_name'],
            'run_date'  => $data['run_date'],
            'notes'     => $data['notes'] ?? null,
            'status'    => OffCyclePayrollRun::STATUS_DRAFT,
        ]);
    }

    public function addItem(OffCyclePayrollRun $run, array $data): OffCyclePayrollItem
    {
        if (! $run->isDraft()) {
            throw new InvalidArgumentException('Items can only be added to draft runs.');
        }

        $item = OffCyclePayrollItem::create([
            'organization_id'          => $run->organization_id,
            'off_cycle_payroll_run_id' => $run->id,
            'employee_id'              => $data['employee_id'],
            'component_code'           => $data['component_code'],
            'component_name'           => $data['component_name'],
            'amount'                   => $data['amount'],
            'tax_amount'               => $data['tax_amount'] ?? 0,
            'net_amount'               => $data['net_amount'],
            'notes'                    => $data['notes'] ?? null,
        ]);

        return $item;
    }

    public function removeItem(OffCyclePayrollRun $run, int $itemId): void
    {
        if (! $run->isDraft()) {
            throw new InvalidArgumentException('Items can only be removed from draft runs.');
        }

        OffCyclePayrollItem::where('id', $itemId)
            ->where('off_cycle_payroll_run_id', $run->id)
            ->delete();
    }

    public function process(OffCyclePayrollRun $run): OffCyclePayrollRun
    {
        if (! $run->canProcess()) {
            throw new InvalidArgumentException('Only draft runs can be processed.');
        }

        return DB::transaction(function () use ($run): OffCyclePayrollRun {
            $run->status = OffCyclePayrollRun::STATUS_PROCESSING;
            $run->save();

            $items = $run->items()->with('employee')->get();

            $totalGross    = $items->sum('amount');
            $totalNet      = $items->sum('net_amount');
            $employeeCount = $items->pluck('employee_id')->unique()->count();

            $run->update([
                'status'         => OffCyclePayrollRun::STATUS_COMPLETED,
                'total_gross'    => $totalGross,
                'total_net'      => $totalNet,
                'employee_count' => $employeeCount,
                'processed_by'   => auth()->id(),
                'processed_at'   => now(),
            ]);

            return $run->fresh();
        });
    }

    public function cancel(OffCyclePayrollRun $run): OffCyclePayrollRun
    {
        if (! $run->canCancel()) {
            throw new InvalidArgumentException('This run cannot be cancelled.');
        }

        $run->update(['status' => OffCyclePayrollRun::STATUS_CANCELLED]);

        return $run->fresh();
    }
}
