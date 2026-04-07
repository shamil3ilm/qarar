<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementInspectionResult extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'procurement_inspection_id',
        'characteristic_name',
        'specification_min',
        'specification_max',
        'actual_value',
        'is_within_spec',
        'defect_description',
    ];

    protected function casts(): array
    {
        return [
            'is_within_spec' => 'boolean',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ProcurementInspection::class, 'procurement_inspection_id');
    }
}
