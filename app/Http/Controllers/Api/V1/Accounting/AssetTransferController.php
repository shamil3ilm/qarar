<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AssetTransfer;
use App\Models\Accounting\FixedAsset;
use App\Services\Accounting\AssetTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetTransferController extends Controller
{
    public function __construct(
        private readonly AssetTransferService $transferService,
    ) {}

    /**
     * List asset transfers involving the organisation (sending or receiving).
     */
    public function index(Request $request): JsonResponse
    {
        $transfers = $this->transferService->index($this->organizationId($request), $request->all());

        return $this->paginated($transfers);
    }

    /**
     * Show a single transfer.
     */
    public function show(AssetTransfer $assetTransfer): JsonResponse
    {
        $assetTransfer->load([
            'fixedAsset:id,uuid,asset_number,asset_name,book_value',
            'receivingAsset:id,uuid,asset_number,asset_name',
            'sendingOrganization:id,name',
            'receivingOrganization:id,name',
            'createdBy:id,name',
        ]);

        return $this->success($assetTransfer);
    }

    /**
     * Initiate an asset transfer (status = pending).
     */
    public function store(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $validated = $request->validate([
            'receiving_organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'transfer_date'             => ['required', 'date'],
            'transfer_type'             => ['sometimes', 'in:book_value,gross_value,negotiated_price'],
            'transfer_price'            => ['required_if:transfer_type,negotiated_price', 'nullable', 'numeric', 'min:0'],
            'notes'                     => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $transfer = $this->transferService->create(
                $fixedAsset,
                $validated,
                $request->user()->id,
            );

            return $this->created($transfer);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Execute a pending transfer — retires asset on sender, creates on receiver.
     */
    public function execute(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->transferService->execute(
                $assetTransfer,
                [
                    'organization_id' => $this->organizationId($request),
                    'branch_id'       => $request->header('X-Branch-Id'),
                    'entry_date'      => $assetTransfer->transfer_date->toDateString(),
                ]
            ),
            'Asset transfer executed successfully.',
            'EXECUTE_FAILED'
        );
    }

    /**
     * Cancel a pending transfer.
     */
    public function cancel(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->tryAction(
            fn() => $this->transferService->cancel($assetTransfer, $validated['reason']),
            'Asset transfer cancelled.',
            'CANCEL_FAILED'
        );
    }
}
