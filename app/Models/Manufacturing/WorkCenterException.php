<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenterException extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_center_id',
        'exception_date',
        'available_hours',
        'reason',
    ];

    protected $casts = [
        'exception_date'  => 'date',
        'available_hours' => 'decimal:2',
    ];

    // Relationships

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    // Helpers

    public function isFullyUnavailable(): bool
    {
        return (float) $this->available_hours === 0.0;
    }
}
