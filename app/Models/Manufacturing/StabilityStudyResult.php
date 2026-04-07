<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StabilityStudyResult extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'stability_study_time_point_id',
        'parameter_name',
        'specification_min',
        'specification_max',
        'result_value',
        'result_text',
        'unit_of_measure',
        'is_pass',
        'tested_by',
    ];

    protected $casts = [
        'specification_min' => 'decimal:4',
        'specification_max' => 'decimal:4',
        'result_value'      => 'decimal:4',
        'is_pass'           => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function timePoint(): BelongsTo
    {
        return $this->belongsTo(StabilityStudyTimePoint::class, 'stability_study_time_point_id');
    }

    public function testedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}
