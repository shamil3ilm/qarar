<?php

declare(strict_types=1);

namespace App\Http\Resources\Projects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectBudgetLineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'uuid'               => $this->uuid,
            'wbs_element_id'     => $this->wbs_element_id,
            'cost_element_id'    => $this->cost_element_id,
            'budgeted_amount'    => (float) $this->budgeted_amount,
            'committed_amount'   => (float) $this->committed_amount,
            'actual_amount'      => (float) $this->actual_amount,
            'available_amount'   => (float) $this->available_amount,
            'avac_action'        => $this->avac_action,
            'tolerance_percent'  => (float) $this->tolerance_percent,
            'utilization_percent' => $this->getUtilizationPercent(),
            'is_over_budget'     => $this->isOverBudget(),
        ];
    }
}
