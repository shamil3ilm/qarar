<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Loyalty\RewardsCatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardsCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RewardsCatalogItem::orderBy('display_order')
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when(
                $request->has('type') || $request->has('reward_type'),
                function ($q) use ($request) {
                    $type = $request->input('type') ?? $request->input('reward_type');
                    $q->where(function ($q) use ($type) {
                        $q->where('reward_type', $type)->orWhere('type', $type);
                    });
                }
            );

        $rewards = $query->paginate($request->input('per_page', 20));
        return $this->paginated($rewards);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'points_required' => 'required|integer|min:1',
            'type' => 'required|string|in:discount,product,voucher,cashback,free_shipping,custom',
            'value' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_path' => 'nullable|string',
            'loyalty_program_id' => 'nullable|integer|exists:loyalty_programs,id',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        // Set reward_type from type alias if not provided
        $validated['reward_type'] = $validated['type'];
        // Set points_cost from points_required alias
        $validated['points_cost'] = $validated['points_required'];

        $reward = RewardsCatalogItem::create($validated);
        return $this->created($reward);
    }

    public function show(RewardsCatalogItem $reward): JsonResponse
    {
        return $this->success($reward);
    }

    public function update(Request $request, RewardsCatalogItem $reward): JsonResponse
    {
        $reward->update($request->all());
        return $this->success($reward->fresh());
    }

    public function destroy(RewardsCatalogItem $reward): JsonResponse
    {
        $reward->delete();
        return $this->success(['message' => 'Reward deleted']);
    }
}
