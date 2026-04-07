<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CheckBook;
use App\Models\Accounting\CheckRegisterEntry;
use App\Services\Accounting\CheckManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckManagementController extends Controller
{
    public function __construct(
        private readonly CheckManagementService $service,
    ) {}

    // -------------------------------------------------------------------------
    // Check Books
    // -------------------------------------------------------------------------

    public function listBooks(Request $request): JsonResponse
    {
        $results = $this->service->listBooks(
            filters: $request->only(['status', 'bank_account_id']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->paginated($results);
    }

    public function createBook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id'    => ['required', 'exists:bank_accounts,id'],
            'check_book_number'  => ['required', 'string', 'max:50'],
            'from_check_number'  => ['required', 'string', 'max:20'],
            'to_check_number'    => ['required', 'string', 'max:20'],
            'issued_date'        => ['nullable', 'date'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $book = $this->service->createBook($validated);

            return $this->created($book, 'Check book created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    public function updateBook(Request $request, string $id): JsonResponse
    {
        $book = CheckBook::findOrFail($id);

        $validated = $request->validate([
            'check_book_number' => ['sometimes', 'string', 'max:50'],
            'status'            => ['sometimes', 'in:active,exhausted,cancelled'],
            'issued_date'       => ['nullable', 'date'],
        ]);

        $book->update($validated);

        return $this->success($book->fresh(), 'Check book updated.');
    }

    public function destroyBook(string $id): JsonResponse
    {
        $book = CheckBook::findOrFail($id);
        $book->delete();

        return $this->success(null, 'Check book deleted.');
    }

    // -------------------------------------------------------------------------
    // Check Register Entries
    // -------------------------------------------------------------------------

    public function listChecks(Request $request): JsonResponse
    {
        $results = $this->service->listChecks(
            filters: $request->only(['status', 'direction', 'check_type']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->paginated($results);
    }

    public function createCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'check_book_id'        => ['nullable', 'exists:check_books,id'],
            'check_number'         => ['nullable', 'string', 'max:20'],
            'check_type'           => ['sometimes', 'in:payment,payroll,refund,other'],
            'direction'            => ['sometimes', 'in:issued,received'],
            'payee_id'             => ['nullable', 'exists:contacts,id'],
            'payment_made_id'      => ['nullable', 'exists:payment_mades,id'],
            'payment_received_id'  => ['nullable', 'exists:payment_receiveds,id'],
            'check_date'           => ['required', 'date'],
            'amount'               => ['required', 'numeric', 'min:0.0001'],
            'currency_code'        => ['nullable', 'string', 'size:3'],
            'memo'                 => ['nullable', 'string'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $check = $this->service->createCheck($validated);

            return $this->created($check, 'Check created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    public function showCheck(string $id): JsonResponse
    {
        $check = CheckRegisterEntry::with(['checkBook', 'payee:id,name'])->findOrFail($id);

        return $this->success($check);
    }

    public function outstanding(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $checks = $this->service->getOutstandingChecks($orgId);

        return $this->success($checks);
    }

    public function printCheck(string $id): JsonResponse
    {
        $check = CheckRegisterEntry::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->print($check),
            'Check marked as printed.',
            'INVALID_STATE',
        );
    }

    public function issue(string $id): JsonResponse
    {
        $check = CheckRegisterEntry::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->issue($check),
            'Check issued.',
            'INVALID_STATE',
        );
    }

    public function markCleared(string $id): JsonResponse
    {
        $check = CheckRegisterEntry::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->markCleared($check),
            'Check cleared.',
            'INVALID_STATE',
        );
    }

    public function markBounced(Request $request, string $id): JsonResponse
    {
        $check = CheckRegisterEntry::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        return $this->tryAction(
            fn() => $this->service->markBounced($check, $validated['reason']),
            'Check marked as bounced.',
            'INVALID_STATE',
        );
    }

    public function cancel(string $id): JsonResponse
    {
        $check = CheckRegisterEntry::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->cancel($check),
            'Check cancelled.',
            'INVALID_STATE',
        );
    }
}
