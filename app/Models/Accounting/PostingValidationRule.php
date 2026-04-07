<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostingValidationRule extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'rule_name',
        'rule_type',
        'trigger_event',
        'conditions',
        'actions',
        'is_active',
        'priority',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions'    => 'array',
            'is_active'  => 'boolean',
            'priority'   => 'integer',
        ];
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('trigger_event', $event);
    }

    public function scopeValidations(Builder $query): Builder
    {
        return $query->where('rule_type', 'validation');
    }

    public function scopeSubstitutions(Builder $query): Builder
    {
        return $query->where('rule_type', 'substitution');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }
}
