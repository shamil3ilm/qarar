<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SeasonalCampaign;
use App\Services\Sales\OffersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonalCampaignController extends Controller
{
    public function __construct(private OffersService $offersService) {}

    public function index(Request $request): JsonResponse
    {
        $campaigns = SeasonalCampaign::where('organization_id', auth()->user()->organization_id)
            ->orderByDesc('starts_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($campaigns);
    }

    public function active(): JsonResponse
    {
        return $this->success($this->offersService->getActiveCampaigns(auth()->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30|unique:seasonal_campaigns,code,NULL,id,organization_id,' . auth()->user()->organization_id,
            'campaign_type' => 'required|string|max:30',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|string|in:percentage,fixed_amount,tiered',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'applies_to' => 'nullable|string|in:all,categories,products,bundles',
            'is_active' => 'nullable|boolean',
            'show_countdown' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        $data = array_merge($request->all(), [
            'organization_id' => auth()->user()->organization_id,
        ]);

        try {
            $campaign = $this->offersService->createCampaign($data);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($campaign);
    }

    public function show(SeasonalCampaign $campaign): JsonResponse
    {
        return $this->success($campaign->load('tierOffers'));
    }

    public function update(Request $request, SeasonalCampaign $campaign): JsonResponse
    {
        $campaign->update($request->all());
        return $this->success($campaign->fresh());
    }

    public function destroy(SeasonalCampaign $campaign): JsonResponse
    {
        $campaign->delete();
        return $this->success(['message' => 'Campaign deleted']);
    }

    public function addTierOffer(Request $request, SeasonalCampaign $campaign): JsonResponse
    {
        $request->validate([
            'tier_name' => 'required|string|max:100',
            'tier_code' => 'nullable|string|max:30',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|in:percentage,fixed_amount',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
        ]);

        $offer = $campaign->tierOffers()->create($request->all());
        return $this->created($offer);
    }
}
