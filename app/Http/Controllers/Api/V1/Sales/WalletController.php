<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Wallet;
use App\Services\Sales\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{

    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallets = Wallet::where('organization_id', $request->user()->organization_id)
            ->with('contact')
            ->when($request->wallet_type, fn ($q, $type) => $q->where('wallet_type', $type))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($wallets);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $wallet = Wallet::where('organization_id', $request->user()->organization_id)
            ->with('contact')
            ->findOrFail($id);

        return $this->success($wallet);
    }

    public function balance(Request $request, int $contactId): JsonResponse
    {
        // Check if the contact exists
        $contactExists = \App\Models\Sales\Contact::where('organization_id', $request->user()->organization_id)
            ->where('id', $contactId)
            ->exists();

        if (!$contactExists) {
            return $this->notFound('Contact not found.');
        }

        $balances = $this->walletService->getBalance(
            $request->user()->organization_id,
            $contactId,
            $request->currency_code
        );

        return $this->success($balances);
    }

    public function credit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
        ]);

        $wallet = Wallet::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        if (!$wallet->is_active) {
            return $this->error('Cannot credit an inactive wallet.', 'WALLET_INACTIVE', 422);
        }

        try {
            $transaction = $this->walletService->credit(
                $wallet,
                (float) $request->amount,
                $request->description
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($transaction, 'Wallet credited successfully.');
    }

    public function debit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
        ]);

        $wallet = Wallet::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        if (!$wallet->is_active) {
            return $this->error('Cannot debit an inactive wallet.', 'WALLET_INACTIVE', 422);
        }

        try {
            $transaction = $this->walletService->debit(
                $wallet,
                (float) $request->amount,
                $request->description
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($transaction, 'Wallet debited successfully.');
    }

    public function statement(Request $request, int $id): JsonResponse
    {
        $wallet = Wallet::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $transactions = $this->walletService->getStatement(
            $wallet,
            $request->from_date,
            $request->to_date,
            $request->integer('per_page', 20)
        );

        return $this->success($transactions);
    }

    public function adjust(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string|max:500',
        ]);

        $wallet = Wallet::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        try {
            $transaction = $this->walletService->adjustBalance(
                $wallet,
                (float) $request->amount,
                $request->description,
                $request->user()->id
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($transaction, 'Wallet adjusted successfully.');
    }
}
