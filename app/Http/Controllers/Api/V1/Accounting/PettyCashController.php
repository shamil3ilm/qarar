<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Finance\PettyCashFund;
use App\Models\Finance\PettyCashReplenishment;
use App\Models\Finance\PettyCashVoucher;
use App\Services\Accounting\PettyCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PettyCashController extends Controller
{
    public function __construct(
        private readonly PettyCashService $pettyCashService
    ) {}

    // -------------------------------------------------------------------------
    // Funds
    // -------------------------------------------------------------------------

    public function indexFunds(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $funds = PettyCashFund::where('organization_id', $organizationId)
            ->with(['custodian', 'branch', 'account'])
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($funds, null, 'Petty cash funds retrieved.');
    }

    public function storeFund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id'             => 'nullable|exists:branches,id',
            'name'                  => 'required|string|max:100',
            'custodian_id'          => 'required|exists:users,id',
            'account_id'            => 'required|exists:accounts,id',
            'opening_balance'       => 'required|numeric|min:0',
            'max_transaction_limit' => 'nullable|numeric|min:0',
            'currency_code'         => 'nullable|string|size:3',
            'is_active'             => 'nullable|boolean',
        ]);

        $fund = PettyCashFund::create(array_merge($validated, [
            'organization_id' => $this->organizationId($request),
            'current_balance' => $validated['opening_balance'],
        ]));

        return $this->success($fund->load(['custodian', 'branch', 'account']), 'Petty cash fund created.', 201);
    }

    public function showFund(PettyCashFund $pettyCashFund): JsonResponse
    {
        return $this->success(
            $pettyCashFund->load(['custodian', 'branch', 'account']),
            'Petty cash fund retrieved.'
        );
    }

    public function updateFund(Request $request, PettyCashFund $pettyCashFund): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'sometimes|string|max:100',
            'custodian_id'          => 'sometimes|exists:users,id',
            'max_transaction_limit' => 'nullable|numeric|min:0',
            'is_active'             => 'nullable|boolean',
        ]);

        $pettyCashFund->update($validated);

        return $this->success($pettyCashFund->fresh()->load(['custodian', 'branch', 'account']), 'Petty cash fund updated.');
    }

    // -------------------------------------------------------------------------
    // Vouchers
    // -------------------------------------------------------------------------

    public function indexVouchers(Request $request, PettyCashFund $pettyCashFund): JsonResponse
    {
        $vouchers = PettyCashVoucher::where('fund_id', $pettyCashFund->id)
            ->with(['account', 'approvedBy', 'creator'])
            ->when($request->input('status'), fn($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn($q, $v) => $q->where('transaction_type', $v))
            ->when($request->input('from_date'), fn($q, $v) => $q->whereDate('voucher_date', '>=', $v))
            ->when($request->input('to_date'), fn($q, $v) => $q->whereDate('voucher_date', '<=', $v))
            ->orderByDesc('voucher_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($vouchers, null, 'Petty cash vouchers retrieved.');
    }

    public function storeVoucher(Request $request, PettyCashFund $pettyCashFund): JsonResponse
    {
        $validated = $request->validate([
            'voucher_date'     => 'nullable|date',
            'transaction_type' => 'required|in:receipt,payment',
            'amount'           => 'required|numeric|min:0.0001',
            'description'      => 'required|string|max:500',
            'category'         => 'nullable|string|max:100',
            'payee_payer'      => 'nullable|string|max:200',
            'receipt_number'   => 'nullable|string|max:100',
            'account_id'       => 'nullable|exists:accounts,id',
        ]);

        $voucher = $this->pettyCashService->createVoucher($pettyCashFund, $validated);

        return $this->success($voucher->load(['account', 'creator']), 'Voucher created.', 201);
    }

    public function approveVoucher(PettyCashVoucher $pettyCashVoucher): JsonResponse
    {
        $voucher = $this->pettyCashService->approveVoucher($pettyCashVoucher);

        return $this->success($voucher->load(['approvedBy']), 'Voucher approved.');
    }

    public function postVoucher(PettyCashVoucher $pettyCashVoucher): JsonResponse
    {
        $voucher = $this->pettyCashService->postVoucher($pettyCashVoucher);

        return $this->success($voucher->load(['fund', 'account']), 'Voucher posted.');
    }

    // -------------------------------------------------------------------------
    // Replenishments
    // -------------------------------------------------------------------------

    public function indexReplenishments(Request $request, PettyCashFund $pettyCashFund): JsonResponse
    {
        $replenishments = PettyCashReplenishment::where('fund_id', $pettyCashFund->id)
            ->with(['requestedBy', 'approvedBy', 'journalEntry'])
            ->when($request->input('status'), fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('replenishment_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($replenishments, null, 'Replenishments retrieved.');
    }

    public function requestReplenishment(Request $request, PettyCashFund $pettyCashFund): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.0001',
            'notes'  => 'nullable|string|max:2000',
        ]);

        $replenishment = $this->pettyCashService->requestReplenishment(
            $pettyCashFund,
            (float) $validated['amount'],
            $validated['notes'] ?? null
        );

        return $this->success($replenishment->load(['requestedBy']), 'Replenishment requested.', 201);
    }

    public function approveReplenishment(PettyCashReplenishment $pettyCashReplenishment): JsonResponse
    {
        $replenishment = $this->pettyCashService->approveReplenishment($pettyCashReplenishment);

        return $this->success($replenishment->load(['approvedBy']), 'Replenishment approved.');
    }

    public function disburseReplenishment(PettyCashReplenishment $pettyCashReplenishment): JsonResponse
    {
        $replenishment = $this->pettyCashService->disburseReplenishment($pettyCashReplenishment);

        return $this->success($replenishment->load(['fund']), 'Replenishment disbursed.');
    }
}
