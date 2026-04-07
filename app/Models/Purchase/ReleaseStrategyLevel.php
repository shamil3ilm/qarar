<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseStrategyLevel extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'release_strategy_id',
        'level',
        'role',
        'min_amount',
        'max_amount',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'level'      => 'integer',
            'min_amount' => 'decimal:4',
            'max_amount' => 'decimal:4',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(ReleaseStrategy::class, 'release_strategy_id');
    }

    /**
     * Check whether the given amount falls within this level's range (if any).
     * A level with no min/max applies to all amounts.
     */
    public function appliesToAmount(float $amount): bool
    {
        if ($this->min_amount !== null && $amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amount > (float) $this->max_amount) {
            return false;
        }

        return true;
    }
}
