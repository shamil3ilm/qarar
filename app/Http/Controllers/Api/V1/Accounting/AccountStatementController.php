<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountStatementController extends Controller
{
    public function __construct(
        private AccountStatementService $statementService,
    ) {}

    /**
     * GET /statements/customers/{contactId}?from=&to=
     */
    public function customerStatement(Request $request, int $contactId): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $statement = $this->statementService->generateCustomerStatement(
            $contactId,
            $validated['from'],
            $validated['to'],
            $request->user()->organization_id,
        );

        return $this->success($statement);
    }

    /**
     * GET /statements/vendors/{contactId}?from=&to=
     */
    public function vendorStatement(Request $request, int $contactId): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $statement = $this->statementService->generateVendorStatement(
            $contactId,
            $validated['from'],
            $validated['to'],
            $request->user()->organization_id,
        );

        return $this->success($statement);
    }

    /**
     * POST /statements/send
     */
    public function sendStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'type'       => ['required', 'in:customer,vendor'],
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date', 'after_or_equal:from'],
            'email'      => ['required', 'email'],
        ]);

        $orgId = $request->user()->organization_id;

        $statement = $validated['type'] === 'customer'
            ? $this->statementService->generateCustomerStatement(
                (int) $validated['contact_id'],
                $validated['from'],
                $validated['to'],
                $orgId,
            )
            : $this->statementService->generateVendorStatement(
                (int) $validated['contact_id'],
                $validated['from'],
                $validated['to'],
                $orgId,
            );

        $this->statementService->sendStatementByEmail($statement, $validated['email']);

        return $this->success(null, 'Statement sent successfully.');
    }

    /**
     * GET /statements/open-items?contact_id=&type=customer|vendor
     */
    public function openItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'type'       => ['required', 'in:customer,vendor'],
        ]);

        $result = $this->statementService->getOpenItems(
            (int) $validated['contact_id'],
            $validated['type'],
            $request->user()->organization_id,
        );

        return $this->success($result);
    }

    /**
     * POST /statements/confirm-reconciliation
     */
    public function confirmReconciliation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'     => ['required', 'integer'],
            'type'           => ['required', 'in:customer,vendor'],
            'confirmed_date' => ['required', 'date'],
        ]);

        $this->statementService->confirmReconciliation(
            (int) $validated['contact_id'],
            $validated['type'],
            $validated['confirmed_date'],
            $request->user()->organization_id,
        );

        return $this->success(null, 'Reconciliation confirmed.');
    }
}
