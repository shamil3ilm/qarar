<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\WithholdingTaxCode;
use App\Models\Accounting\WithholdingTaxLine;
use App\Services\Accounting\WithholdingTaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithholdingTaxController extends Controller
{
    public function __construct(
        private readonly WithholdingTaxService $whtService,
    ) {}

    // =========================================================================
    // WHT Code CRUD
    // =========================================================================

    /**
     * List WHT codes for the organisation.
     */
    public function indexCodes(Request $request): JsonResponse
    {
        $codes = $this->whtService->listCodes($this->organizationId($request), $request->all());

        return $this->paginated($codes);
    }

    /**
     * Create a new WHT code.
     */
    public function storeCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'                  => ['required', 'string', 'max:20'],
            'name'                  => ['required', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:500'],
            'applicable_to'         => ['required', 'in:supplier,customer,both'],
            'rate'                  => ['required', 'numeric', 'min:0', 'max:100'],
            'country_code'          => ['nullable', 'string', 'max:3'],
            'tax_type'              => ['nullable', 'string', 'max:50'],
            'threshold_amount'      => ['nullable', 'numeric', 'min:0'],
            'ceiling_amount'        => ['nullable', 'numeric', 'min:0'],
            'payable_account_id'    => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'receivable_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

        try {
            $code = $this->whtService->createCode($validated, $this->organizationId($request));

            return $this->created($code);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'DUPLICATE_CODE', 422);
        }
    }

    /**
     * Show a single WHT code.
     */
    public function showCode(WithholdingTaxCode $withholdingTaxCode): JsonResponse
    {
        $withholdingTaxCode->load(['payableAccount:id,code,name', 'receivableAccount:id,code,name']);

        return $this->success($withholdingTaxCode);
    }

    /**
     * Update a WHT code.
     */
    public function updateCode(Request $request, WithholdingTaxCode $withholdingTaxCode): JsonResponse
    {
        $validated = $request->validate([
            'code'                  => ['sometimes', 'string', 'max:20'],
            'name'                  => ['sometimes', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:500'],
            'applicable_to'         => ['sometimes', 'in:supplier,customer,both'],
            'rate'                  => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'country_code'          => ['nullable', 'string', 'max:3'],
            'tax_type'              => ['nullable', 'string', 'max:50'],
            'threshold_amount'      => ['nullable', 'numeric', 'min:0'],
            'ceiling_amount'        => ['nullable', 'numeric', 'min:0'],
            'payable_account_id'    => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'receivable_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

        return $this->tryAction(
            fn() => $this->whtService->updateCode($withholdingTaxCode, $validated),
            'Success',
            'UPDATE_FAILED'
        );
    }

    /**
     * Delete a WHT code (soft delete; fails if lines exist).
     */
    public function destroyCode(WithholdingTaxCode $withholdingTaxCode): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->whtService->deleteCode($withholdingTaxCode),
            'WHT code deleted.',
            'DELETE_FAILED'
        );
    }

    // =========================================================================
    // Apply WHT to a payment
    // =========================================================================

    /**
     * Preview (calculate) WHT without posting — used by front-end before confirming.
     */
    public function calculate(Request $request, WithholdingTaxCode $withholdingTaxCode): JsonResponse
    {
        $validated = $request->validate([
            'gross_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $result = $this->whtService->calculate($withholdingTaxCode, (float) $validated['gross_amount']);

        return $this->success($result);
    }

    /**
     * Apply WHT to a posted payment and create the GL entry.
     */
    public function apply(Request $request, WithholdingTaxCode $withholdingTaxCode): JsonResponse
    {
        $validated = $request->validate([
            'payment_type'     => ['required', 'in:payment_received,payment_made'],
            'payment_id'       => ['required', 'integer'],
            'contact_id'       => ['nullable', 'integer'],
            'gross_amount'     => ['required', 'numeric', 'min:0.01'],
            'currency_code'    => ['nullable', 'string', 'max:3'],
            'transaction_date' => ['required', 'date'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $line = $this->whtService->applyToPayment(
                $withholdingTaxCode,
                array_merge($validated, ['organization_id' => $this->organizationId($request)]),
                [
                    'entry_date'  => $validated['transaction_date'],
                    'branch_id'   => $request->header('X-Branch-Id'),
                    'source_type' => 'withholding_tax',
                    'source_id'   => null,
                ]
            );

            return $this->created($line->load('whtCode:id,code,name,rate'));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'APPLY_FAILED', 422);
        }
    }

    // =========================================================================
    // Certificate
    // =========================================================================

    /**
     * Issue a WHT certificate for a specific line.
     */
    public function issueCertificate(Request $request, WithholdingTaxLine $withholdingTaxLine): JsonResponse
    {
        $validated = $request->validate([
            'certificate_date' => ['required', 'date'],
        ]);

        return $this->tryAction(
            fn() => $this->whtService->issueCertificate($withholdingTaxLine, $validated['certificate_date']),
            'WHT certificate issued.',
            'CERTIFICATE_FAILED'
        );
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    /**
     * Summary of WHT deducted / collected per contact for a period.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'         => ['required', 'date'],
            'to'           => ['required', 'date', 'after_or_equal:from'],
            'payment_type' => ['sometimes', 'in:payment_received,payment_made'],
        ]);

        $data = $this->whtService->summary(
            $this->organizationId($request),
            $validated['from'],
            $validated['to'],
            $validated['payment_type'] ?? 'payment_made',
        );

        return $this->success($data);
    }
}
