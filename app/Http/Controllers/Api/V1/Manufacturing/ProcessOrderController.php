<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProcessOrder;
use App\Models\Manufacturing\ProcessOrderPhase;
use App\Models\Manufacturing\Recipe;
use App\Services\Manufacturing\ProcessOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessOrderController extends Controller
{
    public function __construct(
        private readonly ProcessOrderService $service,
    ) {}

    // ── Recipes ───────────────────────────────────────────────────────────────

    /**
     * List process recipes.
     */
    public function recipes(Request $request): JsonResponse
    {
        $query = Recipe::with(['product', 'baseUnit'])
            ->withCount(['phases', 'resources'])
            ->when($request->product_id, fn($q, $v) => $q->forProduct((int) $v))
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderBy('recipe_code');

        $recipes = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($recipes);
    }

    /**
     * Create a new recipe.
     */
    public function storeRecipe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'    => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'recipe_code'   => 'required|string|max:30',
            'name'          => 'required|string|max:255',
            'base_quantity' => 'required|numeric|min:0.0001',
            'base_unit_id'  => 'nullable|exists:units_of_measure,id',
            'recipe_type'   => 'nullable|in:master,control',
            'validity_from' => 'required|date',
            'validity_to'   => 'nullable|date|after_or_equal:validity_from',
            'is_active'     => 'nullable|boolean',
        ]);

        $recipe = Recipe::create($validated);

        return $this->created($recipe->load(['product', 'baseUnit']));
    }

    /**
     * Show a recipe with its phases and resources.
     */
    public function showRecipe(int $id): JsonResponse
    {
        $recipe = Recipe::with(['product', 'baseUnit', 'phases.resources', 'resources.material', 'resources.unit'])
            ->find($id);

        if ($recipe === null) {
            return $this->notFound('Recipe not found.');
        }

        return $this->success($recipe);
    }

    // ── Process Orders ────────────────────────────────────────────────────────

    /**
     * List process orders.
     */
    public function orders(Request $request): JsonResponse
    {
        $query = ProcessOrder::with(['recipe', 'product', 'unit', 'productionVersion'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->product_id, fn($q, $v) => $q->forProduct((int) $v))
            ->when($request->recipe_id, fn($q, $v) => $q->where('recipe_id', $v))
            ->when($request->search, function ($q, $search) {
                $q->where('order_number', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at');

        $orders = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($orders);
    }

    /**
     * Create a process order from a recipe.
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipe_id'             => ['required', Rule::exists('recipes', 'id')->where('organization_id', auth()->user()->organization_id)],
            'planned_quantity'      => 'required|numeric|min:0.0001',
            'unit_id'               => 'nullable|exists:units_of_measure,id',
            'batch_number'          => 'nullable|string|max:50',
            'planned_start'         => 'required|date',
            'planned_finish'        => 'required|date|after_or_equal:planned_start',
            'production_version_id' => 'nullable|exists:production_versions,id',
        ]);

        $order = $this->service->createFromRecipe((int) $validated['recipe_id'], $validated);

        return $this->created($order);
    }

    /**
     * Show a process order with phases and resources.
     */
    public function showOrder(int $id): JsonResponse
    {
        $order = ProcessOrder::with([
            'recipe',
            'product',
            'unit',
            'phases.recipePhase',
            'resources.material',
            'resources.unit',
        ])->find($id);

        if ($order === null) {
            return $this->notFound('Process order not found.');
        }

        return $this->success($order);
    }

    /**
     * Release a process order.
     */
    public function releaseOrder(int $id): JsonResponse
    {
        $order = ProcessOrder::find($id);

        if ($order === null) {
            return $this->notFound('Process order not found.');
        }

        $this->service->release($order);

        return $this->success($order->fresh(), 'Process order released.');
    }

    /**
     * Start a phase.
     */
    public function startPhase(Request $request, int $phaseId): JsonResponse
    {
        $phase = ProcessOrderPhase::find($phaseId);

        if ($phase === null) {
            return $this->notFound('Phase not found.');
        }

        $parameters = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $this->service->startPhase($phase, $parameters);

        return $this->success($phase->fresh(), 'Phase started.');
    }

    /**
     * Complete a phase with actual measurement data.
     */
    public function completePhase(Request $request, int $phaseId): JsonResponse
    {
        $phase = ProcessOrderPhase::find($phaseId);

        if ($phase === null) {
            return $this->notFound('Phase not found.');
        }

        $actuals = $request->validate([
            'actual_temperature'      => 'nullable|numeric',
            'actual_pressure'         => 'nullable|numeric',
            'actual_duration_minutes' => 'nullable|integer|min:0',
            'operator_notes'          => 'nullable|string',
        ]);

        $this->service->completePhase($phase, $actuals);

        return $this->success($phase->fresh(), 'Phase completed.');
    }

    /**
     * Complete an entire process order.
     */
    public function completeOrder(Request $request, int $id): JsonResponse
    {
        $order = ProcessOrder::find($id);

        if ($order === null) {
            return $this->notFound('Process order not found.');
        }

        $validated = $request->validate([
            'actual_quantity' => 'required|numeric|min:0',
        ]);

        $this->service->complete($order, (float) $validated['actual_quantity']);

        return $this->success($order->fresh(), 'Process order completed.');
    }
}
