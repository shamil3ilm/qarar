<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvmBaselineLine extends Model
{
    protected $table = 'project_evm_baseline_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'planned_start'         => 'date',
            'planned_finish'        => 'date',
            'planned_cost'          => 'decimal:2',
            'planned_duration_days' => 'decimal:1',
        ];
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(EvmBaseline::class, 'baseline_id');
    }
}
