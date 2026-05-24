<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkipLotSamplingPlan extends Model
{
    use HasFactory, HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_SKIP_LOT  = 'skip_lot';
    public const TYPE_REDUCED    = 'reduced';
    public const TYPE_NORMAL     = 'normal';
    public const TYPE_TIGHTENED  = 'tightened';

    protected $fillable = [
        'organization_id',
        'plan_code',
        'plan_name',
        'plan_type',
        'inspection_frequency',
        'sample_size_percent',
        'accept_number',
        'reject_number',
        'switch_rule_reduced_to_normal',
        'switch_rule_normal_to_tightened',
        'switch_rule_tightened_to_rejected',
        'is_active',
    ];

    protected $casts = [
        'inspection_frequency'              => 'integer',
        'sample_size_percent'               => 'decimal:2',
        'accept_number'                     => 'integer',
        'reject_number'                     => 'integer',
        'switch_rule_reduced_to_normal'     => 'integer',
        'switch_rule_normal_to_tightened'   => 'integer',
        'switch_rule_tightened_to_rejected' => 'integer',
        'is_active'                         => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(SkipLotDecision::class);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
