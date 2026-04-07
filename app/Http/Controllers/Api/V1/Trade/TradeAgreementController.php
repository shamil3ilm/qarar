<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Trade;

use App\Http\Controllers\Controller;
use App\Models\Trade\PreferentialDutyRate;
use App\Models\Trade\TradeAgreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeAgreementController extends Controller
{
    /**
     * List trade agreements.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeAgreement::query()->orderBy('name')
            ->when($request->boolean('active_only', true), fn($q) => $q->active())
            ->when($request->boolean('effective_only'), fn($q) => $q->effective())
            ->when($request->has('country'), fn($q) => $q->forCountry($request->input('country')))
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });

        $agreements = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($agreements);
    }

    /**
     * Create a trade agreement.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:trade_agreements,code'],
            'description' => ['nullable', 'string'],
            'member_countries' => ['required', 'array', 'min:2'],
            'member_countries.*' => ['string', 'max:3'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $agreement = TradeAgreement::create($validated);

        return $this->created($agreement);
    }

    /**
     * Show a trade agreement.
     */
    public function show(Request $request, TradeAgreement $tradeAgreement): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        if ($tradeAgreement->organization_id && $tradeAgreement->organization_id !== $organizationId) {
            return $this->error('Trade agreement not found.', 'RESOURCE_NOT_FOUND', 404);
        }

        $tradeAgreement->load(['preferentialDutyRates' => fn ($q) => $q->active()->orderBy('tariff_code')]);

        return $this->success($tradeAgreement);
    }

    /**
     * Update a trade agreement.
     */
    public function update(Request $request, TradeAgreement $tradeAgreement): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20', 'unique:trade_agreements,code,' . $tradeAgreement->id],
            'description' => ['nullable', 'string'],
            'member_countries' => ['sometimes', 'array', 'min:2'],
            'member_countries.*' => ['string', 'max:3'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tradeAgreement->update($validated);

        return $this->success($tradeAgreement->fresh(), 'Trade agreement updated successfully');
    }

    /**
     * Delete a trade agreement.
     */
    public function destroy(TradeAgreement $tradeAgreement): JsonResponse
    {
        $tradeAgreement->preferentialDutyRates()->delete();
        $tradeAgreement->delete();

        return $this->success(null, 'Trade agreement deleted successfully');
    }

    /**
     * Add preferential duty rates to an agreement.
     */
    public function addPreferentialRates(Request $request, TradeAgreement $tradeAgreement): JsonResponse
    {
        $validated = $request->validate([
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.tariff_code' => ['required', 'string', 'max:12'],
            'rates.*.origin_country' => ['required', 'string', 'max:3'],
            'rates.*.destination_country' => ['required', 'string', 'max:3'],
            'rates.*.preferential_rate' => ['required', 'numeric', 'min:0'],
            'rates.*.normal_rate' => ['nullable', 'numeric', 'min:0'],
            'rates.*.rule_of_origin' => ['nullable', 'string', 'max:255'],
            'rates.*.effective_from' => ['required', 'date'],
            'rates.*.effective_to' => ['nullable', 'date'],
            'rates.*.is_active' => ['sometimes', 'boolean'],
        ]);

        $createdRates = [];
        foreach ($validated['rates'] as $rateData) {
            $rateData['trade_agreement_id'] = $tradeAgreement->id;
            $createdRates[] = PreferentialDutyRate::create($rateData);
        }

        return $this->created($createdRates);
    }

    /**
     * List preferential rates for an agreement.
     */
    public function preferentialRates(Request $request, TradeAgreement $tradeAgreement): JsonResponse
    {
        $query = $tradeAgreement->preferentialDutyRates()->orderBy('tariff_code')
            ->when($request->boolean('active_only', true), fn($q) => $q->active())
            ->when($request->boolean('effective_only'), fn($q) => $q->effective())
            ->when($request->has('tariff_code'), fn($q) => $q->forTariffCode($request->input('tariff_code')))
            ->when($request->has('origin_country') && $request->has('destination_country'), fn($q) => $q->forRoute($request->input('origin_country'), $request->input('destination_country')));

        $rates = $query->paginate($request->integer('per_page', 50));

        return $this->paginated($rates);
    }

    /**
     * Lookup preferential rate for a specific tariff/route.
     */
    public function lookupPreferentialRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tariff_code' => ['required', 'string', 'max:12'],
            'origin_country' => ['required', 'string', 'max:3'],
            'destination_country' => ['required', 'string', 'max:3'],
        ]);

        $rates = PreferentialDutyRate::active()
            ->effective()
            ->forTariffCode($validated['tariff_code'])
            ->forRoute($validated['origin_country'], $validated['destination_country'])
            ->with('tradeAgreement:id,name,code')
            ->get();

        return $this->success($rates);
    }
}
