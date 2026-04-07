<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\HouseBank;
use App\Models\Accounting\HouseBankAccount;
use App\Models\Accounting\PaymentAdvice;
use App\Services\Accounting\HouseBankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseBankController extends Controller
{
    public function __construct(
        private readonly HouseBankService $houseBankService,
    ) {}

    // =========================================================================
    // House Banks (FI12)
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $banks = $this->houseBankService->listBanks($this->organizationId($request), $request->all());

        return $this->paginated($banks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:20'],
            'name'           => ['required', 'string', 'max:200'],
            'bank_name'      => ['nullable', 'string', 'max:200'],
            'bank_country'   => ['nullable', 'string', 'max:3'],
            'swift_code'     => ['nullable', 'string', 'max:11'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:500'],
            'is_active'      => ['sometimes', 'boolean'],
            'is_default'     => ['sometimes', 'boolean'],
        ]);

        try {
            $bank = $this->houseBankService->createBank($validated, $this->organizationId($request));

            return $this->created($bank);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_FAILED', 422);
        }
    }

    public function show(HouseBank $houseBank): JsonResponse
    {
        $houseBank->load('accounts');

        return $this->success($houseBank);
    }

    public function update(Request $request, HouseBank $houseBank): JsonResponse
    {
        $validated = $request->validate([
            'code'           => ['sometimes', 'string', 'max:20'],
            'name'           => ['sometimes', 'string', 'max:200'],
            'bank_name'      => ['nullable', 'string', 'max:200'],
            'bank_country'   => ['nullable', 'string', 'max:3'],
            'swift_code'     => ['nullable', 'string', 'max:11'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:500'],
            'is_active'      => ['sometimes', 'boolean'],
            'is_default'     => ['sometimes', 'boolean'],
        ]);

        $bank = $this->houseBankService->updateBank($houseBank, $validated);

        return $this->success($bank);
    }

    public function destroy(HouseBank $houseBank): JsonResponse
    {
        return $this->tryAction(
            function () use ($houseBank) {
                $this->houseBankService->deleteBank($houseBank);
            },
            'House bank deleted.',
            'DELETE_FAILED',
        );
    }

    // =========================================================================
    // House Bank Accounts
    // =========================================================================

    public function addAccount(Request $request, HouseBank $houseBank): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id'     => ['nullable', 'integer'],
            'account_id_code'     => ['required', 'string', 'max:20'],
            'currency_code'       => ['nullable', 'string', 'size:3'],
            'account_purpose'     => ['sometimes', 'in:payments,collections,both'],
            'daily_payment_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active'           => ['sometimes', 'boolean'],
        ]);

        $account = $this->houseBankService->addAccount($houseBank, $validated);

        return $this->created($account);
    }

    public function updateAccount(Request $request, HouseBank $houseBank, HouseBankAccount $houseBankAccount): JsonResponse
    {
        $validated = $request->validate([
            'account_id_code'     => ['sometimes', 'string', 'max:20'],
            'currency_code'       => ['nullable', 'string', 'size:3'],
            'account_purpose'     => ['sometimes', 'in:payments,collections,both'],
            'daily_payment_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active'           => ['sometimes', 'boolean'],
        ]);

        $account = $this->houseBankService->updateAccount($houseBankAccount, $validated);

        return $this->success($account);
    }

    public function removeAccount(HouseBank $houseBank, HouseBankAccount $houseBankAccount): JsonResponse
    {
        $this->houseBankService->removeAccount($houseBankAccount);

        return $this->success(null, 'Bank account removed.');
    }

    // =========================================================================
    // Payment Advices (FBZP)
    // =========================================================================

    public function indexAdvices(Request $request): JsonResponse
    {
        $advices = $this->houseBankService->listAdvices($this->organizationId($request), $request->all());

        return $this->paginated($advices);
    }

    public function storeAdvice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction'              => ['required', 'in:outgoing,incoming'],
            'payment_type'           => ['nullable', 'string', 'max:50'],
            'payment_id'             => ['nullable', 'integer'],
            'house_bank_id'          => ['nullable', 'integer'],
            'house_bank_account_id'  => ['nullable', 'integer'],
            'contact_id'             => ['nullable', 'integer'],
            'currency_code'          => ['nullable', 'string', 'size:3'],
            'amount'                 => ['required', 'numeric', 'min:0.01'],
            'payment_date'           => ['required', 'date'],
            'reference'              => ['nullable', 'string', 'max:200'],
            'narration'              => ['nullable', 'string', 'max:500'],
            'advice_number'          => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $advice = $this->houseBankService->createAdvice($validated, $this->organizationId($request));

            return $this->created($advice);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_FAILED', 422);
        }
    }

    public function showAdvice(PaymentAdvice $paymentAdvice): JsonResponse
    {
        $paymentAdvice->load(['houseBank:id,code,name', 'houseBankAccount:id,account_id_code,currency_code']);

        return $this->success($paymentAdvice);
    }

    public function sendAdvice(PaymentAdvice $paymentAdvice): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->houseBankService->sendAdvice($paymentAdvice),
            'Payment advice sent.',
            'SEND_FAILED',
        );
    }

    public function acknowledgeAdvice(PaymentAdvice $paymentAdvice): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->houseBankService->acknowledgeAdvice($paymentAdvice),
            'Payment advice acknowledged.',
            'ACKNOWLEDGE_FAILED',
        );
    }

    public function cancelAdvice(Request $request, PaymentAdvice $paymentAdvice): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->tryAction(
            fn() => $this->houseBankService->cancelAdvice($paymentAdvice, $validated['reason'] ?? ''),
            'Payment advice cancelled.',
            'CANCEL_FAILED',
        );
    }

    public function outstandingSummary(Request $request): JsonResponse
    {
        $data = $this->houseBankService->outstandingSummary($this->organizationId($request));

        return $this->success($data);
    }
}
