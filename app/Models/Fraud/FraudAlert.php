<?php

declare(strict_types=1);

namespace App\Models\Fraud;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudAlert extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    // Status constants
    public const OPEN           = 'open';
    public const REVIEWING      = 'reviewing';
    public const RESOLVED       = 'resolved';
    public const FALSE_POSITIVE = 'false_positive';

    protected $fillable = [
        'organization_id',
        'fraud_rule_id',
        'entity_type',
        'entity_id',
        'entity_uuid',
        'user_id',
        'contact_id',
        'severity',
        'status',
        'fraud_score',
        'evidence',
        'ip_address',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence'    => 'array',
            'reviewed_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    // Relationships
    public function rule(): BelongsTo
    {
        return $this->belongsTo(FraudRule::class, 'fraud_rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Helper
    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isCritical(): bool
    {
        return $this->severity === FraudRule::CRITICAL;
    }

    public function isHighOrAbove(): bool
    {
        return in_array($this->severity, [FraudRule::HIGH, FraudRule::CRITICAL], true);
    }
}
