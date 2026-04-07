<?php

declare(strict_types=1);

namespace App\Models\Fraud;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FraudRule extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization, SoftDeletes;

    // Rule types
    public const VELOCITY   = 'velocity';
    public const AMOUNT     = 'amount';
    public const GEOGRAPHIC = 'geographic';
    public const BEHAVIORAL = 'behavioral';
    public const PATTERN    = 'pattern';

    // Severity levels
    public const LOW      = 'low';
    public const MEDIUM   = 'medium';
    public const HIGH     = 'high';
    public const CRITICAL = 'critical';

    protected $fillable = [
        'organization_id',
        'name',
        'rule_type',
        'entity_type',
        'conditions',
        'severity',
        'is_active',
        'auto_block',
        'score_impact',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active'  => 'boolean',
            'auto_block' => 'boolean',
        ];
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('rule_type', $type);
    }

    public function scopeForEntityType(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }
}
