<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Professional Tax slab configuration for Indian states.
 * PT rates differ per state; slabs are configurable per organization.
 * Fallback static slabs are defined in IndiaEpfEsiService::DEFAULT_PT_SLABS.
 */
class ProfessionalTaxConfig extends Model
{
    use BelongsToOrganization;

    protected $table = 'professional_tax_configs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'salary_from' => 'decimal:2',
            'salary_to'   => 'decimal:2',
            'monthly_tax' => 'decimal:2',
            'is_active'   => 'boolean',
        ];
    }

    public function scopeForState($query, string $stateCode): mixed
    {
        return $query
            ->where('state_code', strtoupper($stateCode))
            ->where('is_active', true)
            ->orderBy('salary_from');
    }
}
