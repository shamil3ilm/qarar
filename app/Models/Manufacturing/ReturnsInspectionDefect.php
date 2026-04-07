<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnsInspectionDefect extends Model
{
    use BelongsToOrganization, HasUuid;

    // Severity constants
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_MAJOR    = 'major';
    public const SEVERITY_MINOR    = 'minor';
    public const SEVERITY_COSMETIC = 'cosmetic';

    protected $fillable = [
        'organization_id',
        'returns_inspection_lot_id',
        'defect_code',
        'defect_description',
        'severity',
        'quantity_affected',
        'recommended_action',
        'actual_action_taken',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'quantity_affected' => 'decimal:4',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function inspectionLot(): BelongsTo
    {
        return $this->belongsTo(ReturnsInspectionLot::class, 'returns_inspection_lot_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }
}
