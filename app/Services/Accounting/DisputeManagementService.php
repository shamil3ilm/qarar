<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CollectionsWorklist;
use App\Models\Accounting\DisputeCase;
use App\Models\Sales\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DisputeManagementService
{
    /**
     * Paginate dispute cases with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = DisputeCase::with(['assignedTo:id,name', 'createdBy:id,name'])
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['dispute_reason'])) {
            $query->where('dispute_reason', $filters['dispute_reason']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Open a new dispute case with an auto-generated case number.
     */
    public function openCase(array $data): DisputeCase
    {
        return DB::transaction(function () use ($data) {
            $caseNumber = $this->generateCaseNumber($data['organization_id']);

            return DisputeCase::create([
                'organization_id' => $data['organization_id'],
                'case_number'     => $caseNumber,
                'document_type'   => $data['document_type'],
                'document_id'     => $data['document_id'],
                'contact_id'      => $data['contact_id'],
                'disputed_amount' => $data['disputed_amount'],
                'resolved_amount' => 0,
                'dispute_reason'  => $data['dispute_reason'] ?? DisputeCase::REASON_OTHER,
                'description'     => $data['description'] ?? null,
                'status'          => DisputeCase::STATUS_OPEN,
                'assigned_to'     => $data['assigned_to'] ?? null,
                'due_date'        => $data['due_date'] ?? null,
                'created_by'      => $data['created_by'],
            ]);
        });
    }

    /**
     * Update status, notes, or assignee on a dispute case.
     */
    public function updateCase(DisputeCase $case, array $data): DisputeCase
    {
        if (in_array($case->status, [DisputeCase::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException('Closed dispute cases cannot be updated.');
        }

        $allowed = ['status', 'assigned_to', 'description', 'due_date', 'dispute_reason'];
        $updates = array_intersect_key($data, array_flip($allowed));

        $case->update($updates);

        return $case->fresh(['assignedTo', 'createdBy']);
    }

    /**
     * Resolve a dispute case with resolution notes and an optional resolved amount.
     */
    public function resolve(DisputeCase $case, array $data): DisputeCase
    {
        if (!in_array($case->status, [DisputeCase::STATUS_OPEN, DisputeCase::STATUS_IN_REVIEW, DisputeCase::STATUS_ESCALATED], true)) {
            throw new InvalidArgumentException('Only open, in-review, or escalated cases can be resolved.');
        }

        return DB::transaction(function () use ($case, $data) {
            $case->update([
                'status'           => DisputeCase::STATUS_RESOLVED,
                'resolved_amount'  => $data['resolved_amount'] ?? $case->disputed_amount,
                'resolution_notes' => $data['resolution_notes'] ?? null,
            ]);

            return $case->fresh(['assignedTo', 'createdBy']);
        });
    }

    /**
     * Permanently close a resolved dispute case.
     */
    public function close(DisputeCase $case): DisputeCase
    {
        if ($case->status !== DisputeCase::STATUS_RESOLVED) {
            throw new InvalidArgumentException('Only resolved cases can be closed.');
        }

        $case->update(['status' => DisputeCase::STATUS_CLOSED]);

        return $case->fresh();
    }

    /**
     * Return the collections worklist with overdue calculations.
     * Enriches each entry with the current overdue total from open invoices.
     */
    public function getCollectionsWorklist(array $filters): LengthAwarePaginator
    {
        $query = CollectionsWorklist::with(['assignedTo:id,name'])
            ->orderByDesc('total_overdue');

        if (!empty($filters['collections_status'])) {
            $query->where('collections_status', $filters['collections_status']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['min_overdue'])) {
            $query->where('total_overdue', '>=', $filters['min_overdue']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Upsert a collections worklist record with a promise-to-pay date and amount.
     */
    public function recordPromiseToPay(int $contactId, array $data): CollectionsWorklist
    {
        return DB::transaction(function () use ($contactId, $data) {
            $worklist = CollectionsWorklist::firstOrNew([
                'organization_id' => $data['organization_id'],
                'contact_id'      => $contactId,
            ]);

            if (!$worklist->exists) {
                $worklist->uuid = (string) \Illuminate\Support\Str::uuid();
            }

            $overdueData = $this->calculateOverdue($data['organization_id'], $contactId);

            $worklist->fill([
                'total_overdue'       => $overdueData['total_overdue'],
                'overdue_days_max'    => $overdueData['overdue_days_max'],
                'collections_status'  => CollectionsWorklist::STATUS_PROMISE_TO_PAY,
                'promise_to_pay_date' => $data['promise_to_pay_date'],
                'promise_amount'      => $data['promise_amount'],
                'assigned_to'         => $data['assigned_to'] ?? $worklist->assigned_to,
                'last_contact_at'     => now(),
                'notes'               => $data['notes'] ?? $worklist->notes,
            ]);

            $worklist->save();

            return $worklist->fresh(['assignedTo']);
        });
    }

    /**
     * Calculate overdue amount and max overdue days for a contact.
     */
    private function calculateOverdue(int $organizationId, int $contactId): array
    {
        $overdueInvoices = Invoice::where('organization_id', $organizationId)
            ->where('customer_id', $contactId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->whereDate('due_date', '<', now())
            ->get(['amount_due', 'due_date']);

        if ($overdueInvoices->isEmpty()) {
            return ['total_overdue' => 0, 'overdue_days_max' => 0];
        }

        $totalOverdue  = $overdueInvoices->sum('amount_due');
        $oldestDueDate = $overdueInvoices->min('due_date');
        $overdueDaysMax = $oldestDueDate ? (int) now()->diffInDays($oldestDueDate) : 0;

        return [
            'total_overdue'   => $totalOverdue,
            'overdue_days_max' => $overdueDaysMax,
        ];
    }

    /**
     * Generate a sequential case number: DISP-YYYYMM-NNNN.
     */
    private function generateCaseNumber(int $organizationId): string
    {
        $prefix = 'DISP-' . now()->format('Ym') . '-';

        $last = DisputeCase::where('organization_id', $organizationId)
            ->where('case_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('case_number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
