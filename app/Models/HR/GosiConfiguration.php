<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GosiConfiguration extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'gosi_configurations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'employee_contribution_pct' => 'decimal:2',
            'employer_contribution_pct' => 'decimal:2',
            'hazard_pct' => 'decimal:2',
            'salary_ceiling' => 'decimal:2',
            'salary_floor' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GosiContribution::class, 'organization_id', 'organization_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    public function isCurrentlyActive(): bool
    {
        $today = now()->toDateString();

        return $this->is_active
            && $this->effective_from->lte(now())
            && ($this->effective_to === null || $this->effective_to->gte(now()));
    }
}
