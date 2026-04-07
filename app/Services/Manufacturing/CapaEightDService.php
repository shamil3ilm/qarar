<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CapaEightD;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CapaEightDService
{
    /** Fields updatable per step, keyed by step identifier (d0-d7). */
    private const STEP_FIELDS = [
        'd0' => ['d0_emergency_response', 'd0_date'],
        'd1' => ['d1_team_members', 'd1_champion_id'],
        'd2' => ['d2_problem_description', 'd2_is_is_not'],
        'd3' => ['d3_containment_actions', 'd3_implemented_date', 'd3_verified'],
        'd4' => ['d4_root_cause', 'd4_escape_point'],
        'd5' => ['d5_corrective_actions'],
        'd6' => ['d6_implementation_plan', 'd6_target_date', 'd6_completed_date', 'd6_verified'],
        'd7' => ['d7_systemic_preventions', 'd7_lessons_learned'],
        'd8' => ['d8_recognition', 'd8_closure_date'],
    ];

    /** Status that corresponds to each step being the current active step. */
    private const STEP_STATUS_MAP = [
        'd0' => CapaEightD::STATUS_D0_OPEN,
        'd1' => CapaEightD::STATUS_D1_TEAM,
        'd2' => CapaEightD::STATUS_D2_PROBLEM,
        'd3' => CapaEightD::STATUS_D3_CONTAINMENT,
        'd4' => CapaEightD::STATUS_D4_ROOT_CAUSE,
        'd5' => CapaEightD::STATUS_D5_ACTIONS,
        'd6' => CapaEightD::STATUS_D6_IMPLEMENTED,
        'd7' => CapaEightD::STATUS_D7_PREVENTION,
        'd8' => CapaEightD::STATUS_D8_CLOSED,
    ];

    public function __construct(
        private readonly NumberGeneratorService $numberGen,
    ) {}

    public function list(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = CapaEightD::where('organization_id', $organizationId)
            ->with(['creator', 'champion']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function create(int $organizationId, array $data, int $userId): CapaEightD
    {
        return DB::transaction(function () use ($organizationId, $data, $userId): CapaEightD {
            $capaNumber = $this->numberGen->generate('8D', null, $organizationId);

            return CapaEightD::create([
                ...$data,
                'organization_id' => $organizationId,
                'capa_number'     => $capaNumber,
                'status'          => CapaEightD::STATUS_D0_OPEN,
                'created_by'      => $userId,
            ]);
        });
    }

    public function find(int $organizationId, string $uuid): CapaEightD
    {
        return CapaEightD::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->with(['creator', 'champion'])
            ->firstOrFail();
    }

    /**
     * Update the fields of a specific discipline step and advance the status.
     *
     * @throws \InvalidArgumentException when step is unknown
     */
    public function updateStep(int $organizationId, string $uuid, string $step, array $data): CapaEightD
    {
        $allowedFields = self::STEP_FIELDS[$step] ?? null;

        if ($allowedFields === null) {
            throw new \InvalidArgumentException("Unknown 8D step: {$step}. Valid steps: d0-d8.");
        }

        return DB::transaction(function () use ($organizationId, $uuid, $step, $data, $allowedFields): CapaEightD {
            $record = CapaEightD::where('organization_id', $organizationId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            $update = array_intersect_key($data, array_flip($allowedFields));
            $update['status'] = self::STEP_STATUS_MAP[$step];

            $record->update($update);

            return $record->fresh(['creator', 'champion']);
        });
    }

    public function close(int $organizationId, string $uuid, array $data): CapaEightD
    {
        return DB::transaction(function () use ($organizationId, $uuid, $data): CapaEightD {
            $record = CapaEightD::where('organization_id', $organizationId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            $record->update([
                'status'          => CapaEightD::STATUS_D8_CLOSED,
                'd8_recognition'  => $data['d8_recognition'] ?? $record->d8_recognition,
                'd8_closure_date' => $data['d8_closure_date'] ?? now()->toDateString(),
            ]);

            return $record->fresh();
        });
    }
}
