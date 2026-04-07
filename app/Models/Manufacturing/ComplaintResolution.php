<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintResolution extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'complaint_id', 'resolution_type', 'resolution_description',
        'customer_accepted', 'resolution_date', 'resolved_by_id',
    ];

    protected $casts = [
        'resolution_date'   => 'date',
        'customer_accepted' => 'boolean',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
