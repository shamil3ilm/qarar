<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\EmployeeExit;
use App\Models\HR\ExitClearanceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExitManagementService
{
    /**
     * Default clearance items generated when clearance starts.
     */
    private const DEFAULT_CLEARANCE_ITEMS = [
        ['clearance_item' => 'IT Assets Return', 'sort_order' => 1],
        ['clearance_item' => 'Access Card / Badge', 'sort_order' => 2],
        ['clearance_item' => 'Office Keys / Locker', 'sort_order' => 3],
        ['clearance_item' => 'Company Vehicle', 'sort_order' => 4],
        ['clearance_item' => 'Pending Tasks Handover', 'sort_order' => 5],
        ['clearance_item' => 'Knowledge Transfer', 'sort_order' => 6],
        ['clearance_item' => 'Finance Clearance', 'sort_order' => 7],
        ['clearance_item' => 'HR Documents', 'sort_order' => 8],
    ];

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = EmployeeExit::query()
            ->with(['employee', 'initiator', 'approver'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['employee_id']), fn($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['exit_type']), fn($q) => $q->where('exit_type', $filters['exit_type']))
            ->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function initiate(array $data): EmployeeExit
    {
        return EmployeeExit::create([
            'employee_id'          => $data['employee_id'],
            'exit_type'            => $data['exit_type'] ?? EmployeeExit::TYPE_RESIGNATION,
            'resignation_date'     => $data['resignation_date'] ?? null,
            'last_working_date'    => $data['last_working_date'] ?? null,
            'notice_period_days'   => $data['notice_period_days'] ?? 30,
            'notice_period_waived' => $data['notice_period_waived'] ?? false,
            'exit_reason'          => $data['exit_reason'] ?? null,
            'status'               => EmployeeExit::STATUS_INITIATED,
            'initiated_by'         => auth()->id(),
        ]);
    }

    public function approve(EmployeeExit $exit, int $approvedBy): EmployeeExit
    {
        if ($exit->status !== EmployeeExit::STATUS_INITIATED) {
            throw new InvalidArgumentException('Only initiated exits can be approved.');
        }

        $exit->update([
            'status'      => EmployeeExit::STATUS_NOTICE_PERIOD,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $exit->fresh();
    }

    public function startClearance(EmployeeExit $exit): EmployeeExit
    {
        if (! in_array($exit->status, [EmployeeExit::STATUS_NOTICE_PERIOD, EmployeeExit::STATUS_INITIATED], true)) {
            throw new InvalidArgumentException('Clearance can only be started for exits in notice period or initiated status.');
        }

        return DB::transaction(function () use ($exit): EmployeeExit {
            $exit->update(['status' => EmployeeExit::STATUS_CLEARANCE_IN_PROGRESS]);

            foreach (self::DEFAULT_CLEARANCE_ITEMS as $item) {
                ExitClearanceItem::create([
                    'organization_id'  => $exit->organization_id,
                    'employee_exit_id' => $exit->id,
                    'clearance_item'   => $item['clearance_item'],
                    'sort_order'       => $item['sort_order'],
                    'status'           => ExitClearanceItem::STATUS_PENDING,
                ]);
            }

            return $exit->fresh(['clearanceItems']);
        });
    }

    public function clearItem(ExitClearanceItem $item, ?string $remarks): ExitClearanceItem
    {
        if ($item->status !== ExitClearanceItem::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending items can be cleared.');
        }

        $item->clear($remarks);

        return $item->fresh();
    }

    public function completeClearance(EmployeeExit $exit): EmployeeExit
    {
        if ($exit->status !== EmployeeExit::STATUS_CLEARANCE_IN_PROGRESS) {
            throw new InvalidArgumentException('Exit is not in clearance-in-progress status.');
        }

        $pendingItems = $exit->clearanceItems()
            ->where('status', ExitClearanceItem::STATUS_PENDING)
            ->count();

        if ($pendingItems > 0) {
            throw new InvalidArgumentException("There are {$pendingItems} pending clearance item(s). All items must be cleared or waived.");
        }

        $exit->update(['status' => EmployeeExit::STATUS_CLEARANCE_COMPLETE]);

        return $exit->fresh();
    }

    public function settle(EmployeeExit $exit, array $settlementData): EmployeeExit
    {
        if (! in_array($exit->status, [EmployeeExit::STATUS_CLEARANCE_COMPLETE, EmployeeExit::STATUS_CLEARANCE_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('Exit must be in clearance-complete status to settle.');
        }

        $exit->update([
            'status'                   => EmployeeExit::STATUS_SETTLED,
            'final_settlement_amount'  => $settlementData['final_settlement_amount'] ?? null,
            'settlement_date'          => $settlementData['settlement_date'] ?? now()->toDateString(),
            'eosb_amount'              => $settlementData['eosb_amount'] ?? null,
            'leave_encashment_amount'  => $settlementData['leave_encashment_amount'] ?? null,
        ]);

        return $exit->fresh();
    }

    public function close(EmployeeExit $exit): EmployeeExit
    {
        if ($exit->status === EmployeeExit::STATUS_CLOSED) {
            throw new InvalidArgumentException('Exit is already closed.');
        }

        $exit->update(['status' => EmployeeExit::STATUS_CLOSED]);

        return $exit->fresh();
    }
}
