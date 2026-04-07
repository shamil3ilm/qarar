<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BenefitType extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'benefit_types';

    protected $guarded = ['id'];

    public const CATEGORY_ALLOWANCE = 'allowance';
    public const CATEGORY_INSURANCE = 'insurance';
    public const CATEGORY_OTHER = 'other';

    public const CALC_FIXED = 'fixed';
    public const CALC_PERCENTAGE = 'percentage';

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:4',
            'percentage_basis' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'eligibility_rules' => 'array',
        ];
    }

    public function employeeBenefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class, 'benefit_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
