<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialInsuranceScheme extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'social_insurance_schemes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'employee_contribution_pct' => 'decimal:2',
            'employer_contribution_pct' => 'decimal:2',
            'work_hazard_pct' => 'decimal:2',
            'salary_ceiling' => 'decimal:4',
            'salary_floor' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(SocialInsuranceRecord::class, 'scheme_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SocialInsuranceSubmission::class, 'scheme_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }
}
