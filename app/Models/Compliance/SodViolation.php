<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SodViolation extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'grc_sod_violations';

    public const STATUS_OPEN          = 'open';
    public const STATUS_RISK_ACCEPTED = 'risk_accepted';
    public const STATUS_MITIGATED     = 'mitigated';
    public const STATUS_REMEDIATED    = 'remediated';

    protected $fillable = [
        'organization_id',
        'conflict_id',
        'user_id',
        'status',
        'mitigation_description',
        'accepted_by',
        'accepted_at',
        'review_date',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'detected_at' => 'datetime',
            'review_date' => 'date',
        ];
    }

    public function conflict(): BelongsTo
    {
        return $this->belongsTo(SodConflict::class, 'conflict_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
