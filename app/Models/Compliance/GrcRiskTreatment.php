<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrcRiskTreatment extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'grc_risk_treatments';

    // Treatment type constants
    public const TREATMENT_AVOID    = 'avoid';
    public const TREATMENT_REDUCE   = 'reduce';
    public const TREATMENT_TRANSFER = 'transfer';
    public const TREATMENT_ACCEPT   = 'accept';

    // Status constants
    public const STATUS_PLANNED     = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'organization_id',
        'risk_id',
        'treatment_type',
        'description',
        'action_plan',
        'target_date',
        'completed_date',
        'status',
        'owner_id',
        'target_likelihood',
        'target_impact',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_date'        => 'date',
            'completed_date'     => 'date',
            'target_likelihood'  => 'integer',
            'target_impact'      => 'integer',
        ];
    }

    public function risk(): BelongsTo
    {
        return $this->belongsTo(GrcRisk::class, 'risk_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
