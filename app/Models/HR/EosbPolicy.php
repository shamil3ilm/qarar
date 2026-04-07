<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EosbPolicy extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'eosb_policies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'min_service_months' => 'integer',
            'first_period_days_per_year' => 'decimal:2',
            'first_period_years' => 'integer',
            'subsequent_days_per_year' => 'decimal:2',
            'prorate_partial_year' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(EosbProvision::class, 'eosb_policy_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(EosbSettlement::class, 'eosb_policy_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
