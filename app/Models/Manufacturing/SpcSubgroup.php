<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpcSubgroup extends Model
{
    protected $table = 'spc_subgroups';

    protected $fillable = [
        'spc_chart_id',
        'organization_id',
        'measured_at',
        'measurements',
        'subgroup_mean',
        'subgroup_range',
        'out_of_control',
        'violated_rules',
        'recorded_by',
    ];

    protected $casts = [
        'measurements'   => 'array',
        'violated_rules' => 'array',
        'out_of_control' => 'boolean',
        'measured_at'    => 'datetime',
        'subgroup_mean'  => 'decimal:6',
        'subgroup_range' => 'decimal:6',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(SpcChart::class, 'spc_chart_id');
    }
}
