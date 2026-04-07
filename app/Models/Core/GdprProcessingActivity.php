<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GdprProcessingActivity extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'activity_name', 'purpose', 'legal_basis',
        'data_categories', 'recipient_categories', 'retention_period_days',
        'third_country_transfers', 'dpia_required',
    ];

    protected $casts = [
        'data_categories'        => 'array',
        'recipient_categories'   => 'array',
        'third_country_transfers' => 'boolean',
        'dpia_required'          => 'boolean',
    ];
}
