<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MmToleranceCheckResult extends Model
{
    use BelongsToOrganization, HasUuid;

    public const RESULT_PASS = 'pass';
    public const RESULT_WARNING = 'warning';
    public const RESULT_BLOCK = 'block';

    protected $fillable = [
        'organization_id',
        'bill_id',
        'tolerance_key_id',
        'check_type',
        'expected_value',
        'actual_value',
        'deviation',
        'deviation_pct',
        'result',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'deviation' => 'decimal:4',
            'deviation_pct' => 'decimal:4',
            'checked_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function toleranceKey(): BelongsTo
    {
        return $this->belongsTo(MmToleranceKey::class, 'tolerance_key_id');
    }

    public function isBlocking(): bool
    {
        return $this->result === self::RESULT_BLOCK;
    }
}
