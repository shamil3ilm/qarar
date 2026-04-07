<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\GrIrClearingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrIrClearingController extends Controller
{
    public function __construct(private GrIrClearingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->getOpenItems(
            $this->organizationId($request),
            $request->get('as_of_date')
        );

        return $this->success($result);
    }

    public function clear(Request $request, int $poLineId): JsonResponse
    {
        $validated = $request->validate([
            'clearing_date'       => 'nullable|date',
            'clearing_type'       => 'nullable|in:write_off,adjust_qty',
            'grir_account_code'   => 'nullable|string|max:20',
            'offset_account_code' => 'nullable|string|max:20',
        ]);

        try {
            $result = $this->service->clearVariance(
                $this->organizationId($request),
                $poLineId,
                $validated,
                (int) auth()->id()
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'CLEARING_FAILED', 422);
        }

        return $this->success($result, 'GR/IR variance cleared.');
    }

    public function report(Request $request): JsonResponse
    {
        $result = $this->service->getReport(
            $this->organizationId($request),
            $request->get('as_of_date')
        );

        return $this->success($result);
    }
}
