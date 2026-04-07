<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\BillingInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = BillingInvoice::where('organization_id', auth()->user()->organization_id)
            ->with('subscription.plan')
            ->orderByDesc('invoice_date')
            ->paginate($request->input('per_page', 20));

        return $this->paginated($invoices);
    }

    public function show(BillingInvoice $invoice): JsonResponse
    {
        return $this->success($invoice->load('items', 'payments'));
    }

    public function pay(BillingInvoice $invoice): JsonResponse
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'amount_paid' => $invoice->total,
            'amount_due' => 0,
        ]);

        return $this->success($invoice->fresh());
    }
}
