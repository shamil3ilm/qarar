<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Loyalty\CustomerLoyaltyAccount;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyAccountController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $accounts = CustomerLoyaltyAccount::with('contact', 'tier', 'loyaltyProgram')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($accounts);
    }

    public function enroll(Request $request): JsonResponse
    {
        $account = $this->loyaltyService->enrollCustomer(
            $request->input('contact_id'),
            $request->input('program_id')
        );
        return $this->created($account->load('contact', 'loyaltyProgram'));
    }

    public function show(CustomerLoyaltyAccount $account): JsonResponse
    {
        return $this->success($account->load('contact', 'tier', 'loyaltyProgram'));
    }

    public function transactions(CustomerLoyaltyAccount $account): JsonResponse
    {
        $transactions = $account->transactions()->orderByDesc('created_at')->paginate(20);
        return $this->paginated($transactions);
    }

    public function earnPoints(Request $request, CustomerLoyaltyAccount $account): JsonResponse
    {
        $transaction = $this->loyaltyService->earnPoints(
            $account->id,
            $request->input('points'),
            $request->input('description', 'Manual points award'),
            $request->input('source_type'),
            $request->input('source_id'),
            $request->input('source_amount')
        );
        return $this->created($transaction);
    }

    public function redeemReward(Request $request, CustomerLoyaltyAccount $account): JsonResponse
    {
        $redemption = $this->loyaltyService->redeemReward(
            $account->id,
            $request->input('reward_id')
        );
        return $this->created($redemption->load('reward'));
    }

    public function availableRewards(CustomerLoyaltyAccount $account): JsonResponse
    {
        $rewards = $this->loyaltyService->getAvailableRewards($account->id);
        return $this->success($rewards);
    }
}
