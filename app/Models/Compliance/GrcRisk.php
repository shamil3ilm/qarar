<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrcRisk extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'grc_risks';

    // Status constants
    public const STATUS_IDENTIFIED = 'identified';
    public const STATUS_ASSESSED   = 'assessed';
    public const STATUS_TREATED    = 'treated';
    public const STATUS_MONITORED  = 'monitored';
    public const STATUS_CLOSED     = 'closed';
    public const STATUS_ACCEPTED   = 'accepted';

    // Risk type constants
    public const TYPE_STRATEGIC    = 'strategic';
    public const TYPE_OPERATIONAL  = 'operational';
    public const TYPE_FINANCIAL    = 'financial';
    public const TYPE_COMPLIANCE   = 'compliance';
    public const TYPE_REPUTATIONAL = 'reputational';
    public const TYPE_IT           = 'it';
    public const TYPE_EHS          = 'ehs';

    protected $fillable = [
        'organization_id',
        'risk_number',
        'title',
        'description',
        'category_id',
        'risk_type',
        'inherent_likelihood',
        'inherent_impact',
        'inherent_score',
        'residual_likelihood',
        'residual_impact',
        'residual_score',
        'risk_status',
        'risk_owner_id',
        'module_reference',
        'existing_controls',
        'next_review_date',
        'identified_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inherent_likelihood' => 'integer',
            'inherent_impact'     => 'integer',
            'inherent_score'      => 'integer',
            'residual_likelihood' => 'integer',
            'residual_impact'     => 'integer',
            'residual_score'      => 'integer',
            'next_review_date'    => 'date',
            'identified_date'     => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GrcRiskCategory::class, 'category_id');
    }

    public function riskOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'risk_owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(GrcRiskTreatment::class, 'risk_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(GrcRiskReview::class, 'risk_id');
    }

    public function kris(): HasMany
    {
        return $this->hasMany(GrcKri::class, 'risk_id');
    }
}
