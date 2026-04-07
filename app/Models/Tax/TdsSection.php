<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TdsSection extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'threshold_amount' => 'decimal:4',
            'rate_individual'  => 'decimal:2',
            'rate_company'     => 'decimal:2',
            'rate_no_pan'      => 'decimal:2',
            'is_active'        => 'boolean',
        ];
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(TdsDeduction::class, 'section_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get applicable TDS rate based on deductee type.
     */
    public function getRateForDeducteeType(string $deducteeType): float
    {
        return match ($deducteeType) {
            'company' => (float) $this->rate_company,
            default   => (float) $this->rate_individual,
        };
    }
}
