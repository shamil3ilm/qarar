<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\EcmAffectedObject;
use App\Models\Manufacturing\EngineeringChange;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EngineeringChangeService
{
    public function list(array $filters = []): Collection
    {
        return EngineeringChange::with(['requestedBy', 'approvedBy', 'affectedObjects'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['change_type']), fn($q) => $q->where('change_type', $filters['change_type']))
            ->when(isset($filters['priority']), fn($q) => $q->where('priority', $filters['priority']))
            ->when(isset($filters['product_id']), fn($q) => $q->forProduct((int) $filters['product_id']))
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $data): EngineeringChange
    {
        return EngineeringChange::create($data);
    }

    public function update(EngineeringChange $ec, array $data): EngineeringChange
    {
        $ec->update($data);

        return $ec->fresh();
    }

    public function submit(EngineeringChange $ec): EngineeringChange
    {
        if (!$ec->canSubmit()) {
            throw ValidationException::withMessages([
                'status' => "Engineering change cannot be submitted in its current status: {$ec->status}.",
            ]);
        }

        $ec->update(['status' => EngineeringChange::STATUS_SUBMITTED]);

        return $ec->fresh();
    }

    public function approve(EngineeringChange $ec, int $approvedBy): EngineeringChange
    {
        if (!$ec->canApprove()) {
            throw ValidationException::withMessages([
                'status' => "Engineering change cannot be approved in its current status: {$ec->status}.",
            ]);
        }

        $ec->update([
            'status' => EngineeringChange::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $ec->fresh();
    }

    public function reject(EngineeringChange $ec, int $rejectedBy, string $reason): EngineeringChange
    {
        if (!$ec->canApprove()) {
            throw ValidationException::withMessages([
                'status' => "Engineering change cannot be rejected in its current status: {$ec->status}.",
            ]);
        }

        $ec->update([
            'status' => EngineeringChange::STATUS_REJECTED,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'reason' => $ec->reason ? $ec->reason . "\nRejection reason: " . $reason : "Rejection reason: " . $reason,
        ]);

        return $ec->fresh();
    }

    public function implement(EngineeringChange $ec): EngineeringChange
    {
        if (!$ec->canImplement()) {
            throw ValidationException::withMessages([
                'status' => "Engineering change cannot be implemented in its current status: {$ec->status}.",
            ]);
        }

        $ec->update([
            'status' => EngineeringChange::STATUS_IMPLEMENTED,
            'implemented_at' => now(),
        ]);

        return $ec->fresh();
    }

    public function addAffectedObject(EngineeringChange $ec, array $data): EcmAffectedObject
    {
        return $ec->affectedObjects()->create([
            'organization_id' => $ec->organization_id,
            ...$data,
        ]);
    }

    public function getChangesForObject(string $objectType, int $objectId): Collection
    {
        return EngineeringChange::whereHas('affectedObjects', function ($q) use ($objectType, $objectId): void {
            $q->where('object_type', $objectType)->where('object_id', $objectId);
        })
            ->with(['requestedBy', 'approvedBy'])
            ->orderByDesc('created_at')
            ->get();
    }
}
