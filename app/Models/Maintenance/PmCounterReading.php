<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmCounterReading extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'uuid', 'organization_id', 'counter_id', 'reading_value',
        'reading_date', 'delta_value', 'recorded_by',
    ];

    protected $casts = [
        'reading_value' => 'decimal:3',
        'delta_value'   => 'decimal:3',
        'reading_date'  => 'datetime',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(PmCounter::class, 'counter_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
