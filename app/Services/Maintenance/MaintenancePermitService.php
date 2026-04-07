<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\MaintenancePermit;
use App\Models\Maintenance\PermitSafetyCheck;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class MaintenancePermitService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = MaintenancePermit::with(['requestedByUser', 'approvedByUser', 'maintenanceOrder'])
            ->where('organization_id', $orgId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['permit_type'])) {
            $query->where('permit_type', $filters['permit_type']);
        }

        if (isset($filters['maintenance_order_id'])) {
            $query->where('maintenance_order_id', $filters['maintenance_order_id']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    public function create(int $orgId, array $data): MaintenancePermit
    {
        $permit = MaintenancePermit::create(array_merge($data, [
            'organization_id' => $orgId,
            'status'          => MaintenancePermit::STATUS_REQUESTED,
        ]));

        $this->generateDefaultChecks($permit);

        return $permit->load('safetyChecks');
    }

    public function update(MaintenancePermit $permit, array $data): MaintenancePermit
    {
        $this->assertEditableStatus($permit);
        $permit->update($data);
        return $permit->fresh();
    }

    public function approve(MaintenancePermit $permit, int $approvedBy): MaintenancePermit
    {
        if ($permit->status !== MaintenancePermit::STATUS_REQUESTED) {
            throw new RuntimeException('Only requested permits can be approved.');
        }

        $permit->update([
            'status'      => MaintenancePermit::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => Carbon::now(),
        ]);

        return $permit->fresh();
    }

    public function activate(MaintenancePermit $permit): MaintenancePermit
    {
        if ($permit->status !== MaintenancePermit::STATUS_APPROVED) {
            throw new RuntimeException('Only approved permits can be activated.');
        }

        $permit->update([
            'status'     => MaintenancePermit::STATUS_ACTIVE,
            'valid_from' => $permit->valid_from ?? Carbon::now(),
        ]);

        return $permit->fresh();
    }

    public function suspend(MaintenancePermit $permit): MaintenancePermit
    {
        if ($permit->status !== MaintenancePermit::STATUS_ACTIVE) {
            throw new RuntimeException('Only active permits can be suspended.');
        }

        $permit->update(['status' => MaintenancePermit::STATUS_SUSPENDED]);

        return $permit->fresh();
    }

    public function close(MaintenancePermit $permit, int $closedBy): MaintenancePermit
    {
        if (!in_array($permit->status, [MaintenancePermit::STATUS_ACTIVE, MaintenancePermit::STATUS_SUSPENDED], true)) {
            throw new RuntimeException('Only active or suspended permits can be closed.');
        }

        $incompleteRequired = $permit->safetyChecks()
            ->where('is_mandatory', true)
            ->where('is_completed', false)
            ->count();

        if ($incompleteRequired > 0) {
            throw new RuntimeException("Cannot close permit: {$incompleteRequired} mandatory safety check(s) are incomplete.");
        }

        $permit->update([
            'status'    => MaintenancePermit::STATUS_CLOSED,
            'closed_by' => $closedBy,
            'closed_at' => Carbon::now(),
        ]);

        return $permit->fresh();
    }

    public function addSafetyCheck(MaintenancePermit $permit, array $data): PermitSafetyCheck
    {
        return PermitSafetyCheck::create(array_merge($data, [
            'organization_id'       => $permit->organization_id,
            'maintenance_permit_id' => $permit->id,
        ]));
    }

    public function completeSafetyCheck(PermitSafetyCheck $check, int $userId): PermitSafetyCheck
    {
        if ($check->is_completed) {
            throw new RuntimeException('Safety check is already completed.');
        }

        $check->update([
            'is_completed' => true,
            'completed_by' => $userId,
            'completed_at' => Carbon::now(),
        ]);

        return $check->fresh();
    }

    public function generateDefaultChecks(MaintenancePermit $permit): void
    {
        $checks = $this->defaultChecksByType($permit->permit_type);

        foreach ($checks as $index => $description) {
            PermitSafetyCheck::create([
                'organization_id'       => $permit->organization_id,
                'maintenance_permit_id' => $permit->id,
                'check_description'     => $description,
                'is_mandatory'          => true,
                'is_completed'          => false,
                'sort_order'            => $index,
            ]);
        }
    }

    private function assertEditableStatus(MaintenancePermit $permit): void
    {
        $editableStatuses = [MaintenancePermit::STATUS_REQUESTED, MaintenancePermit::STATUS_APPROVED];

        if (!in_array($permit->status, $editableStatuses, true)) {
            throw new RuntimeException('Permit cannot be edited in its current status.');
        }
    }

    private function defaultChecksByType(string $permitType): array
    {
        return match ($permitType) {
            MaintenancePermit::TYPE_HOT_WORK => [
                'Fire extinguisher available and inspected',
                'Flammable materials removed from area',
                'Combustible materials protected or removed',
                'Fire watch assigned',
                'Hot work area inspected after completion',
            ],
            MaintenancePermit::TYPE_CONFINED_SPACE => [
                'Atmospheric testing completed (O2, LEL, CO)',
                'Isolation and lockout/tagout applied',
                'Rescue equipment available',
                'Attendant assigned outside confined space',
                'Communication plan established',
            ],
            MaintenancePermit::TYPE_ELECTRICAL_ISOLATION => [
                'Lockout/Tagout procedure applied',
                'Energy verification performed',
                'Hazardous energy sources identified',
                'All affected personnel notified',
                'Re-energization procedure reviewed',
            ],
            MaintenancePermit::TYPE_HEIGHT_WORK => [
                'Fall protection equipment inspected',
                'Scaffold or elevated platform inspected',
                'Area below barricaded',
                'Weather conditions assessed',
                'Emergency rescue plan in place',
            ],
            MaintenancePermit::TYPE_CHEMICAL => [
                'SDS reviewed and available',
                'Appropriate PPE identified and available',
                'Spill containment equipment available',
                'Emergency shower/eye wash accessible',
                'Disposal procedure confirmed',
            ],
            default => [
                'Work area inspected and safe',
                'Appropriate PPE identified',
                'Hazards communicated to workers',
                'Emergency contacts available',
            ],
        };
    }
}
