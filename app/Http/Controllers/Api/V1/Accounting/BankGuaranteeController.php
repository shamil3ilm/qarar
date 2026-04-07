<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankGuarantee;
use App\Services\Accounting\BankGuaranteeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankGuaranteeController extends Controller
{
    public function __construct(
        private readonly BankGuaranteeService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $results = $this->service->list(
            filters: $request->only(['status', 'direction', 'guarantee_type', 'search']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->paginated($results);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guarantee_number'           => ['required', 'string', 'max:50'],
            'guarantee_type'             => ['sometimes', 'in:bid_bond,performance_bond,advance_payment,retention,financial'],
            'direction'                  => ['sometimes', 'in:issued,received'],
            'bank_id'                    => ['nullable', 'exists:contacts,id'],
            'beneficiary_id'             => ['nullable', 'exists:contacts,id'],
            'applicant_id'               => ['nullable', 'exists:contacts,id'],
            'related_purchase_order_id'  => ['nullable', 'exists:purchase_orders,id'],
            'related_sales_order_id'     => ['nullable', 'exists:sales_orders,id'],
            'currency_code'              => ['nullable', 'string', 'size:3'],
            'amount'                     => ['required', 'numeric', 'min:0.0001'],
            'bank_charges'               => ['nullable', 'numeric', 'min:0'],
            'issue_date'                 => ['required', 'date'],
            'expiry_date'                => ['nullable', 'date', 'after_or_equal:issue_date'],
            'claim_deadline'             => ['nullable', 'date'],
            'is_auto_renewed'            => ['nullable', 'boolean'],
            'renewal_period_days'        => ['nullable', 'integer', 'min:1'],
            'notes'                      => ['nullable', 'string'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $guarantee = $this->service->create($validated);

            return $this->created($guarantee, 'Bank guarantee created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    public function show(string $id): JsonResponse
    {
        $guarantee = BankGuarantee::findOrFail($id);

        $guarantee->load(['bank:id,name', 'beneficiary:id,name', 'applicant:id,name']);

        return $this->success($guarantee);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $guarantee = BankGuarantee::findOrFail($id);

        $validated = $request->validate([
            'guarantee_type'            => ['sometimes', 'in:bid_bond,performance_bond,advance_payment,retention,financial'],
            'bank_id'                   => ['nullable', 'exists:contacts,id'],
            'beneficiary_id'            => ['nullable', 'exists:contacts,id'],
            'applicant_id'              => ['nullable', 'exists:contacts,id'],
            'related_purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'related_sales_order_id'    => ['nullable', 'exists:sales_orders,id'],
            'currency_code'             => ['nullable', 'string', 'size:3'],
            'amount'                    => ['sometimes', 'numeric', 'min:0.0001'],
            'bank_charges'              => ['nullable', 'numeric', 'min:0'],
            'issue_date'                => ['sometimes', 'date'],
            'expiry_date'               => ['nullable', 'date'],
            'claim_deadline'            => ['nullable', 'date'],
            'is_auto_renewed'           => ['nullable', 'boolean'],
            'renewal_period_days'       => ['nullable', 'integer', 'min:1'],
            'notes'                     => ['nullable', 'string'],
        ]);

        return $this->tryAction(
            fn() => $this->service->update($guarantee, $validated),
            'Bank guarantee updated successfully.',
            'INVALID_STATE'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $guarantee = BankGuarantee::findOrFail($id);
        $guarantee->delete();

        return $this->success(null, 'Bank guarantee deleted.');
    }

    public function activate(string $id): JsonResponse
    {
        $guarantee = BankGuarantee::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->activate($guarantee),
            'Bank guarantee activated.',
            'INVALID_STATE'
        );
    }

    public function claim(Request $request, string $id): JsonResponse
    {
        $guarantee = BankGuarantee::findOrFail($id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.0001'],
            'reason' => ['required', 'string'],
        ]);

        return $this->tryAction(
            fn() => $this->service->claim($guarantee, (float) $validated['amount'], $validated['reason']),
            'Bank guarantee claim recorded.',
            'CLAIM_FAILED'
        );
    }

    public function returnGuarantee(string $id): JsonResponse
    {
        $guarantee = BankGuarantee::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->return($guarantee),
            'Bank guarantee returned.',
            'INVALID_STATE'
        );
    }

    public function expiringSoon(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $guarantees = $this->service->getExpiringSoon($days);

        return $this->success($guarantees);
    }
}
