<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Loyalty\LoyaltyProgram;
use App\Models\Loyalty\PointsEarningRule;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyProgramController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService)
    {
    }

    public function index(): JsonResponse
    {
        $programs = $this->loyaltyService->getPrograms(auth()->user()->organization_id);
        return $this->success($programs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'point_value' => 'required|numeric|gt:0',
            'earn_rate' => 'required|numeric|gt:0',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
            'currency_name' => 'nullable|string|max:50',
            'currency_symbol' => 'nullable|string|max:10',
            'min_redeem_points' => 'nullable|integer|min:0',
            'points_expiry_days' => 'nullable|integer|min:0',
        ]);

        $program = $this->loyaltyService->createProgram($request->all());
        return $this->created($program);
    }

    public function show(LoyaltyProgram $program): JsonResponse
    {
        return $this->success($program->load('tiers', 'earningRules'));
    }

    public function update(Request $request, LoyaltyProgram $program): JsonResponse
    {
        $program->update($request->all());
        return $this->success($program->fresh());
    }

    public function tiers(LoyaltyProgram $program): JsonResponse
    {
        return $this->success($program->tiers()->orderBy('tier_level')->get());
    }

    public function storeTier(Request $request, LoyaltyProgram $program): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:30',
            'min_points' => 'nullable|integer|min:0',
        ]);

        $tier = $program->tiers()->create(array_merge(
            $request->all(),
            ['organization_id' => auth()->user()->organization_id]
        ));
        return $this->created($tier);
    }

    public function earningRules(LoyaltyProgram $program): JsonResponse
    {
        return $this->success($program->earningRules);
    }

    public function destroy(LoyaltyProgram $program): JsonResponse
    {
        $program->delete();
        return $this->success(null, 'Loyalty program deleted successfully.');
    }

    public function storeEarningRule(Request $request, LoyaltyProgram $program): JsonResponse
    {
        $rule = PointsEarningRule::create(array_merge(
            $request->all(),
            ['loyalty_program_id' => $program->id, 'organization_id' => auth()->user()->organization_id]
        ));
        return $this->created($rule);
    }
}
