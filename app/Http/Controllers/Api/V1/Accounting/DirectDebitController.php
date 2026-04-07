<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\DirectDebitCollection;
use App\Models\Accounting\DirectDebitMandate;
use App\Services\Accounting\DirectDebitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectDebitController extends Controller
{
    public function __construct(
        private readonly DirectDebitService $service,
    ) {}

    // -------------------------------------------------------------------------
    // Mandates
    // -------------------------------------------------------------------------

    public function listMandates(Request $request): JsonResponse
    {
        $results = $this->service->list(
            filters: $request->only(['status', 'direction']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->paginated($results);
    }

    public function createMandate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mandate_reference'      => ['required', 'string', 'max:50'],
            'mandate_type'           => ['sometimes', 'in:core,b2b,standing_order'],
            'direction'              => ['sometimes', 'in:collection,payment'],
            'counterparty_id'        => ['required', 'exists:contacts,id'],
            'bank_account_id'        => ['nullable', 'exists:bank_accounts,id'],
            'iban'                   => ['nullable', 'string', 'max:34'],
            'bic'                    => ['nullable', 'string', 'max:11'],
            'currency_code'          => ['nullable', 'string', 'size:3'],
            'amount'                 => ['nullable', 'numeric', 'min:0.0001'],
            'frequency'              => ['sometimes', 'in:weekly,biweekly,monthly,quarterly,annually,one_time'],
            'first_collection_date'  => ['nullable', 'date'],
            'max_collections'        => ['nullable', 'integer', 'min:1'],
            'signed_date'            => ['nullable', 'date'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $mandate = $this->service->create($validated);

            return $this->created($mandate, 'Direct debit mandate created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    public function showMandate(string $id): JsonResponse
    {
        $mandate = DirectDebitMandate::with(['counterparty:id,name', 'bankAccount:id,account_name'])->findOrFail($id);

        return $this->success($mandate);
    }

    public function updateMandate(Request $request, string $id): JsonResponse
    {
        $mandate = DirectDebitMandate::findOrFail($id);

        $validated = $request->validate([
            'iban'                  => ['nullable', 'string', 'max:34'],
            'bic'                   => ['nullable', 'string', 'max:11'],
            'currency_code'         => ['nullable', 'string', 'size:3'],
            'amount'                => ['nullable', 'numeric', 'min:0.0001'],
            'frequency'             => ['sometimes', 'in:weekly,biweekly,monthly,quarterly,annually,one_time'],
            'first_collection_date' => ['nullable', 'date'],
            'next_collection_date'  => ['nullable', 'date'],
            'max_collections'       => ['nullable', 'integer', 'min:1'],
            'signed_date'           => ['nullable', 'date'],
        ]);

        return $this->tryAction(
            fn() => $this->service->update($mandate, $validated),
            'Mandate updated.',
            'INVALID_STATE',
        );
    }

    public function activate(string $id): JsonResponse
    {
        $mandate = DirectDebitMandate::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->activate($mandate),
            'Mandate activated.',
            'INVALID_STATE',
        );
    }

    public function pause(string $id): JsonResponse
    {
        $mandate = DirectDebitMandate::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->pause($mandate),
            'Mandate paused.',
            'INVALID_STATE',
        );
    }

    public function cancelMandate(string $id): JsonResponse
    {
        $mandate = DirectDebitMandate::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->cancel($mandate),
            'Mandate cancelled.',
            'INVALID_STATE',
        );
    }

    public function collections(string $id): JsonResponse
    {
        $mandate = DirectDebitMandate::findOrFail($id);

        $collections = DirectDebitCollection::where('direct_debit_mandate_id', $mandate->id)
            ->orderByDesc('collection_date')
            ->paginate(20);

        return $this->paginated($collections);
    }

    public function dueCollections(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $collections = $this->service->getDueCollections($orgId);

        return $this->success($collections);
    }

    public function generateCollections(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $created = $this->service->generateCollections($orgId);

        return $this->success([
            'generated' => count($created),
            'items'     => $created,
        ], count($created) . ' collection(s) generated.');
    }

    public function processCollection(string $collectionId): JsonResponse
    {
        $collection = DirectDebitCollection::findOrFail($collectionId);

        return $this->tryAction(
            fn() => $this->service->processCollection($collection),
            'Collection processed.',
            'INVALID_STATE',
        );
    }
}
