<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AssetComponent;
use App\Models\Accounting\FixedAsset;
use App\Services\Accounting\AssetComponentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssetComponentController extends Controller
{
    public function __construct(
        private AssetComponentService $componentService
    ) {}

    /**
     * List components for a fixed asset.
     */
    public function index(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $query = $fixedAsset->components()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('acquisition_date');

        return $this->paginated($query->paginate($request->integer('per_page', 50)));
    }

    /**
     * Show a single component.
     */
    public function show(FixedAsset $fixedAsset, AssetComponent $assetComponent): JsonResponse
    {
        $this->authorizeComponent($fixedAsset, $assetComponent);
        $assetComponent->load(['journalEntry:id,entry_number', 'createdBy:id,name']);

        return $this->success($assetComponent);
    }

    /**
     * Add a component to a fixed asset (SAP AS02 sub-asset).
     */
    public function store(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'acquisition_date'   => ['required', 'date'],
            'acquisition_cost'   => ['required', 'numeric', 'min:0.01'],
            'salvage_value'      => ['nullable', 'numeric', 'min:0'],
            'useful_life_years'  => ['nullable', 'numeric', 'min:0.1'],
            'depreciation_method' => ['nullable', 'in:straight_line,declining_balance,sum_of_years_digits,units_of_production'],
            'component_number'   => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $component = $this->componentService->addComponent($fixedAsset, $validated, auth()->id());

            return $this->created($component, 'Component added successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'COMPONENT_FAILED', 422);
        }
    }

    /**
     * Retire (partially dispose) a component — SAP partial retirement (ABAVN).
     */
    public function retire(Request $request, FixedAsset $fixedAsset, AssetComponent $assetComponent): JsonResponse
    {
        $this->authorizeComponent($fixedAsset, $assetComponent);

        $validated = $request->validate([
            'proceeds_amount'  => ['nullable', 'numeric', 'min:0'],
            'retirement_date'  => ['required', 'date'],
            'reason'           => ['required', 'string', 'max:255'],
        ]);

        try {
            $component = $this->componentService->retireComponent(
                $assetComponent,
                (float) ($validated['proceeds_amount'] ?? 0),
                $validated['retirement_date'],
                $validated['reason'],
                auth()->id()
            );

            return $this->success($component, 'Component retired successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'RETIREMENT_FAILED', 422);
        }
    }

    /**
     * Delete a component (only active components with no journal entries).
     */
    public function destroy(FixedAsset $fixedAsset, AssetComponent $assetComponent): JsonResponse
    {
        $this->authorizeComponent($fixedAsset, $assetComponent);

        if ($assetComponent->status !== AssetComponent::STATUS_ACTIVE) {
            return $this->error('Only active components can be deleted.', 'CANNOT_DELETE', 400);
        }

        if ($assetComponent->journal_entry_id !== null) {
            return $this->error(
                'Components with GL postings cannot be deleted. Retire the component instead.',
                'CANNOT_DELETE',
                400
            );
        }

        $assetComponent->delete();

        return $this->success(null, 'Component deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function authorizeComponent(FixedAsset $fixedAsset, AssetComponent $component): void
    {
        if ($component->fixed_asset_id !== $fixedAsset->id) {
            abort(404, 'Component not found on this asset.');
        }
    }
}
