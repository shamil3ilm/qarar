<?php

declare(strict_types=1);

namespace App\Models\Loyalty;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsEarningRule extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'loyalty_program_id', 'name', 'description',
        'trigger_type', 'bonus_points', 'bonus_multiplier', 'conditions',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'bonus_multiplier' => 'decimal:2',
        'conditions' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }
}
