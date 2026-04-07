<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayGrade extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'grade_code',
        'grade_name',
        'min_salary',
        'mid_salary',
        'max_salary',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:4',
            'mid_salary' => 'decimal:4',
            'max_salary' => 'decimal:4',
        ];
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function isInRange(float $salary): bool
    {
        return $salary >= (float) $this->min_salary && $salary <= (float) $this->max_salary;
    }

    public function scopeForCurrency($query, string $currency)
    {
        return $query->where('currency_code', $currency);
    }
}
