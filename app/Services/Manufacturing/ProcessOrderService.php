<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProcessOrder;
use App\Models\Manufacturing\ProcessOrderPhase;
use App\Models\Manufacturing\ProcessOrderResource;
use App\Models\Manufacturing\Recipe;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

class ProcessOrderService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Create a process order by exploding a recipe into phases and resources.
     */
    public function createFromRecipe(int $recipeId, array $data): ProcessOrder
    {
        $recipe = Recipe::with(['phases', 'resources'])->findOrFail($recipeId);

        return DB::transaction(function () use ($recipe, $data): ProcessOrder {
            $scaleFactor = (float) $data['planned_quantity'] / (float) $recipe->base_quantity;

            $order = ProcessOrder::create([
                'organization_id'       => auth()->user()->organization_id,
                'recipe_id'             => $recipe->id,
                'product_id'            => $recipe->product_id,
                'order_number'          => $this->numberGenerator->generate('PRO'),
                'planned_quantity'      => $data['planned_quantity'],
                'unit_id'               => $data['unit_id'] ?? $recipe->base_unit_id,
                'batch_number'          => $data['batch_number'] ?? null,
                'planned_start'         => $data['planned_start'],
                'planned_finish'        => $data['planned_finish'],
                'status'                => ProcessOrder::STATUS_CREATED,
                'production_version_id' => $data['production_version_id'] ?? null,
                'created_by'            => auth()->id(),
            ]);

            // Explode recipe phases into order phases
            foreach ($recipe->phases as $recipePhase) {
                $order->phases()->create([
                    'recipe_phase_id' => $recipePhase->id,
                    'phase_number'    => $recipePhase->phase_number,
                    'name'            => $recipePhase->name,
                    'status'          => ProcessOrderPhase::STATUS_PENDING,
                ]);
            }

            // Explode recipe resources, scaled to planned quantity
            foreach ($recipe->resources as $recipeResource) {
                $order->resources()->create([
                    'recipe_resource_id' => $recipeResource->id,
                    'material_id'        => $recipeResource->material_id,
                    'planned_quantity'   => round((float) $recipeResource->quantity * $scaleFactor, 4),
                    'unit_id'            => $recipeResource->unit_id,
                ]);
            }

            return $order->load(['phases', 'resources', 'recipe', 'product']);
        });
    }

    /**
     * Release a process order (makes it available for production).
     */
    public function release(ProcessOrder $order): void
    {
        if (!$order->canBeReleased()) {
            throw new \LogicException("Process order {$order->order_number} cannot be released in its current status.");
        }

        $order->update(['status' => ProcessOrder::STATUS_RELEASED]);
    }

    /**
     * Start a single phase of a process order, recording actual parameters.
     *
     * @param array<string, mixed> $parameters
     */
    public function startPhase(ProcessOrderPhase $phase, array $parameters = []): void
    {
        if (!$phase->isPending()) {
            throw new \LogicException("Phase {$phase->phase_number} is not in pending status.");
        }

        $phase->update([
            'status'     => ProcessOrderPhase::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        // Transition the parent order to in_progress on first phase start
        $order = $phase->processOrder;
        if ($order->isReleased()) {
            $order->update(['status' => ProcessOrder::STATUS_IN_PROGRESS, 'actual_start' => now()]);
        }
    }

    /**
     * Complete a phase, recording actual measurement values.
     *
     * @param array<string, mixed> $actuals
     */
    public function completePhase(ProcessOrderPhase $phase, array $actuals): void
    {
        if (!$phase->isInProgress()) {
            throw new \LogicException("Phase {$phase->phase_number} is not in progress.");
        }

        $startedAt = $phase->started_at;
        $actualDurationMinutes = $startedAt
            ? (int) $startedAt->diffInMinutes(now())
            : null;

        $phase->update([
            'status'                  => ProcessOrderPhase::STATUS_COMPLETED,
            'completed_at'            => now(),
            'actual_temperature'      => $actuals['actual_temperature'] ?? null,
            'actual_pressure'         => $actuals['actual_pressure'] ?? null,
            'actual_duration_minutes' => $actuals['actual_duration_minutes'] ?? $actualDurationMinutes,
            'operator_notes'          => $actuals['operator_notes'] ?? null,
        ]);
    }

    /**
     * Complete the entire process order, recording the actual produced quantity.
     */
    public function complete(ProcessOrder $order, float $actualQuantity): void
    {
        if (!$order->canBeCompleted()) {
            throw new \LogicException("Process order {$order->order_number} cannot be completed in its current status.");
        }

        $order->update([
            'status'          => ProcessOrder::STATUS_COMPLETED,
            'actual_quantity' => $actualQuantity,
            'actual_finish'   => now(),
        ]);
    }

    /**
     * Return per-phase progress for an order.
     *
     * @return array{
     *   order_id: int,
     *   status: string,
     *   total_phases: int,
     *   completed_phases: int,
     *   progress_percent: float,
     *   phases: list<array<string, mixed>>
     * }
     */
    public function getPhaseProgress(int $orderId): array
    {
        $order = ProcessOrder::with('phases')->findOrFail($orderId);

        $total     = $order->phases->count();
        $completed = $order->phases->where('status', ProcessOrderPhase::STATUS_COMPLETED)->count();

        return [
            'order_id'        => $order->id,
            'status'          => $order->status,
            'total_phases'    => $total,
            'completed_phases' => $completed,
            'progress_percent' => $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
            'phases'          => $order->phases->map(fn($p) => [
                'id'           => $p->id,
                'phase_number' => $p->phase_number,
                'name'         => $p->name,
                'status'       => $p->status,
                'started_at'   => $p->started_at?->toIso8601String(),
                'completed_at' => $p->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
