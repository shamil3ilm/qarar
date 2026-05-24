<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CarryForwardRun;
use App\Services\Accounting\CarryForwardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarryForwardController extends Controller
{
    public function __construct(
        private CarryForwardService $service
    ) {}

    /**
     * Execute a year-end carry forward run.
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
            'to_fiscal_year_id'   => ['required', 'exists:fiscal_years,id', 'different:from_fiscal_year_id'],
            'run_type'            => ['nullable', 'in:balance_sheet,profit_loss,both'],
        ]);

        try {
            $run = $this->service->executeCarryForward([
                ...$validated,
                'organization_id' => $this->organizationId($request),
                'executed_by'     => auth()->id(),
            ]);

            return $this->created($run, 'Carry forward executed successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'CARRY_FORWARD_FAILED', 422);
        } catch (ModelNotFoundException $e) {
            return $this->error('Fiscal year not found.', 'NOT_FOUND', 404);
        } catch (\Throwable $e) {
            return $this->error('Carry forward failed: ' . $e->getMessage(), 'CARRY_FORWARD_ERROR', 500);
        }
    }

    /**
     * Get the status and details of a carry forward run.
     */
    public function status(CarryForwardRun $carryForwardRun): JsonResponse
    {
        $run = $this->service->getCarryForwardStatus($carryForwardRun);

        return $this->success($run);
    }
}
