<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customs;

use App\Http\Controllers\Controller;
use App\Models\Customs\CustomsTariffCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomsTariffController extends Controller
{
    /**
     * List tariff codes with search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomsTariffCode::query()->orderBy('code')
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->has('chapter'), fn($q) => $q->forChapter($request->input('chapter')))
            ->when($request->has('country_code'), fn($q) => $q->forCountry($request->input('country_code')))
            ->when($request->boolean('active_only', true), fn($q) => $q->active());

        $tariffs = $query->paginate($request->integer('per_page', 50));

        return $this->paginated($tariffs);
    }

    /**
     * Show a single tariff code.
     */
    public function show(int $tariffCode): JsonResponse
    {
        $tariff = CustomsTariffCode::find($tariffCode);

        if (!$tariff) {
            return $this->notFound('Tariff code not found');
        }

        return $this->success($tariff);
    }

    /**
     * Create a new tariff code.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12'],
            'description' => ['required', 'string', 'max:255'],
            'chapter' => ['nullable', 'string', 'max:10'],
            'heading' => ['nullable', 'string', 'max:10'],
            'subheading' => ['nullable', 'string', 'max:12'],
            'country_code' => ['nullable', 'string', 'max:3'],
            'duty_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'specific_duty' => ['nullable', 'numeric', 'min:0'],
            'specific_duty_unit' => ['nullable', 'string', 'max:20'],
            'duty_type' => ['nullable', 'in:ad_valorem,specific,composite,mixed'],
            'excise_rate' => ['nullable', 'numeric', 'min:0'],
            'requires_license' => ['nullable', 'boolean'],
            'is_prohibited' => ['nullable', 'boolean'],
            'is_restricted' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Auto-derive chapter, heading, subheading from code
        $code = $validated['code'];
        if (strlen($code) >= 2 && empty($validated['chapter'])) {
            $validated['chapter'] = substr($code, 0, 2);
        }
        if (strlen($code) >= 4 && empty($validated['heading'])) {
            $validated['heading'] = substr($code, 0, 4);
        }
        if (strlen($code) >= 6 && empty($validated['subheading'])) {
            $validated['subheading'] = substr($code, 0, 6);
        }

        $tariff = CustomsTariffCode::create($validated);

        return $this->created($tariff);
    }

    /**
     * Update a tariff code.
     */
    public function update(Request $request, int $tariffCode): JsonResponse
    {
        $tariff = CustomsTariffCode::find($tariffCode);

        if (!$tariff) {
            return $this->notFound('Tariff code not found');
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:12'],
            'description' => ['sometimes', 'string', 'max:255'],
            'chapter' => ['nullable', 'string', 'max:10'],
            'heading' => ['nullable', 'string', 'max:10'],
            'subheading' => ['nullable', 'string', 'max:12'],
            'country_code' => ['nullable', 'string', 'max:3'],
            'duty_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'specific_duty' => ['nullable', 'numeric', 'min:0'],
            'specific_duty_unit' => ['nullable', 'string', 'max:20'],
            'duty_type' => ['nullable', 'in:ad_valorem,specific,composite,mixed'],
            'excise_rate' => ['nullable', 'numeric', 'min:0'],
            'requires_license' => ['nullable', 'boolean'],
            'is_prohibited' => ['nullable', 'boolean'],
            'is_restricted' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $tariff->update($validated);

        return $this->success($tariff->fresh(), 'Tariff code updated successfully');
    }

    /**
     * Delete a tariff code.
     */
    public function destroy(int $tariffCode): JsonResponse
    {
        $tariff = CustomsTariffCode::find($tariffCode);

        if (!$tariff) {
            return $this->notFound('Tariff code not found');
        }

        $tariff->delete();

        return $this->success(null, 'Tariff code deleted.');
    }

    /**
     * Lookup tariff by HS code.
     */
    public function lookup(Request $request, string $code): JsonResponse
    {
        $countryCode = $request->input('country_code');

        $query = CustomsTariffCode::active()
            ->searchByCode($code);

        if (!empty($countryCode)) {
            $query->where(function ($q) use ($countryCode) {
                $q->forCountry($countryCode)
                    ->orWhereNull('country_code');
            });
        }

        $tariffs = $query->orderBy('code')->limit(20)->get();

        return $this->success($tariffs);
    }
}
