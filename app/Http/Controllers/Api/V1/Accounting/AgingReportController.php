<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AgingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgingReportController extends Controller
{
    public function __construct(private readonly AgingReportService $service) {}

    /**
     * AR Aging report: outstanding customer invoices bucketed by days overdue.
     *
     * GET /accounting/reports/ar-aging?as_of_date=YYYY-MM-DD
     */
    public function arAging(Request $request): JsonResponse
    {
        $asOf = $request->input('as_of_date');
        $data = $this->service->getArAging(
            $request->user()->organization_id,
            $asOf
        );

        return $this->success($data);
    }

    /**
     * AP Aging report: outstanding vendor bills bucketed by days overdue.
     *
     * GET /accounting/reports/ap-aging?as_of_date=YYYY-MM-DD
     */
    public function apAging(Request $request): JsonResponse
    {
        $asOf = $request->input('as_of_date');
        $data = $this->service->getApAging(
            $request->user()->organization_id,
            $asOf
        );

        return $this->success($data);
    }
}
