<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SodConflict extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'grc_sod_conflicts';

    public const RISK_CRITICAL = 'critical';
    public const RISK_HIGH     = 'high';
    public const RISK_MEDIUM   = 'medium';
    public const RISK_LOW      = 'low';

    protected $fillable = [
        'organization_id',
        'function_a_id',
        'function_b_id',
        'risk_level',
        'description',
        'mitigation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function functionA(): BelongsTo
    {
        return $this->belongsTo(SodFunction::class, 'function_a_id');
    }

    public function functionB(): BelongsTo
    {
        return $this->belongsTo(SodFunction::class, 'function_b_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(SodViolation::class, 'conflict_id');
    }
}
