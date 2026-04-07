<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimePolicy extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'policy_name',
        'daily_standard_hours',
        'weekly_standard_hours',
        'ot_rate_weekday',
        'ot_rate_weekend',
        'ot_rate_holiday',
        'max_daily_ot_hours',
        'max_weekly_ot_hours',
        'requires_approval',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'daily_standard_hours'  => 'decimal:2',
            'weekly_standard_hours' => 'decimal:2',
            'ot_rate_weekday'       => 'decimal:2',
            'ot_rate_weekend'       => 'decimal:2',
            'ot_rate_holiday'       => 'decimal:2',
            'max_daily_ot_hours'    => 'decimal:2',
            'max_weekly_ot_hours'   => 'decimal:2',
            'requires_approval'     => 'boolean',
            'is_active'             => 'boolean',
        ];
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'policy_id');
    }

    public function getRateForDayType(string $dayType): float
    {
        return match ($dayType) {
            'weekend' => (float) $this->ot_rate_weekend,
            'holiday' => (float) $this->ot_rate_holiday,
            default   => (float) $this->ot_rate_weekday,
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
