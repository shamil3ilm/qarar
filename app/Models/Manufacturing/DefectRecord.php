<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefectRecord extends Model
{
    use HasFactory;

    // Severity constants
    public const SEVERITY_MINOR = 'minor';
    public const SEVERITY_MAJOR = 'major';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'quality_notification_id',
        'defect_type',
        'defect_code',
        'quantity',
        'severity',
        'description',
        'location',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // Relationships

    public function notification(): BelongsTo
    {
        return $this->belongsTo(QualityNotification::class, 'quality_notification_id');
    }

    // Helper Methods

    public function isMinor(): bool
    {
        return $this->severity === self::SEVERITY_MINOR;
    }

    public function isMajor(): bool
    {
        return $this->severity === self::SEVERITY_MAJOR;
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }
}
