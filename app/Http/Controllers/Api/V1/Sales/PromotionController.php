<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\CouponCode;
use App\Models\Sales\Promotion;
use App\Services\Sales\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromotionController extends Controller
{

    public function __construct(
        protected PromotionService $promotionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $promotions = Promotion::where('organization_id', $request->user()->organization_id)
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->when($request->type, fn ($q, $type) => $q->where('discount_type', $type))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($promotions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->all();

        // Validate unique code within organization
        $uniqueCodeRule = 'nullable|string|max:50';
        if (!empty($data['code'])) {
            $uniqueCodeRule = 'nullable|string|max:50|unique:promotions,code,NULL,id,organization_id,' . $request->user()->organization_id;
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'code' => $uniqueCodeRule,
            'description' => 'nullable|string|max:2000',
            'type' => 'required|in:percentage,fixed_amount,fixed_price,buy_x_get_y,bundle,tiered,free_shipping',
            'apply_to' => 'nullable|in:line,order,shipping',
            'target' => 'nullable|in:all,specific_products,specific_categories,specific_customers,customer_groups',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'get_discount_percent' => 'nullable|numeric|min:0|max:100',
            'tiers' => 'nullable|array',
            'min_order_amount' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_customer' => 'nullable|integer|min:1',
            'is_stackable' => 'nullable|boolean',
            'is_exclusive' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'requires_code' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'customer_ids' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $promotion = Promotion::create(array_merge(
                $data,
                [
                    'organization_id' => $request->user()->organization_id,
                    'created_by' => $request->user()->id,
                ]
            ));
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($promotion, 'Promotion created successfully.');
    }

    public function show(Request $request, Promotion $promotion): JsonResponse
    {
        return $this->success($promotion);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion->update($request->all());

        return $this->success($promotion->fresh(), 'Promotion updated.');
    }

    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return $this->success(null, 'Promotion deleted.');
    }

    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'order_amount' => 'nullable|numeric|min:0',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        try {
            $promotion = $this->promotionService->validatePromoCode(
                $request->user()->organization_id,
                $request->code,
                $request->contact_id
            );

            if (!$promotion) {
                return $this->error('Invalid or expired promotion code.', 'INVALID_PROMO_CODE', 422);
            }

            // Check minimum order amount if provided
            $orderAmount = (float) ($request->order_amount ?? 0);
            if ($promotion->min_order_amount && $orderAmount < (float) $promotion->min_order_amount) {
                return $this->error(
                    "Minimum order amount of {$promotion->min_order_amount} required.",
                    'MIN_ORDER_NOT_MET',
                    422
                );
            }

            return $this->success([
                'valid' => true,
                'promotion' => $promotion,
                'discount_type' => $promotion->type,
                'discount_value' => $promotion->discount_value,
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }
    }

    public function generateCoupons(Request $request, Promotion $promotion): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:1000',
            'prefix' => 'nullable|string|max:10',
            'max_uses_each' => 'nullable|integer|min:1',
        ]);

        try {
            $codes = $this->promotionService->generateCouponCodes(
                $promotion->id,
                $request->integer('quantity'),
                $request->prefix,
                $request->integer('max_uses_each', 1)
            );
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($codes, 'Coupon codes generated.');
    }

    public function coupons(Request $request, Promotion $promotion): JsonResponse
    {
        $coupons = CouponCode::where('promotion_id', $promotion->id)
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($coupons);
    }

    public function analytics(Request $request, Promotion $promotion): JsonResponse
    {
        $analytics = $this->promotionService->getAnalytics($promotion->id);

        return $this->success($analytics);
    }
}
