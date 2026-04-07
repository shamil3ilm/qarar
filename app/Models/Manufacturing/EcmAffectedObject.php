<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcmAffectedObject extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'engineering_change_id',
        'object_type',
        'object_id',
        'object_reference',
        'change_description',
        'before_value',
        'after_value',
    ];

    protected $casts = [
        'before_value' => 'array',
        'after_value' => 'array',
        'object_id' => 'integer',
    ];

    // Relationships

    public function engineeringChange(): BelongsTo
    {
        return $this->belongsTo(EngineeringChange::class);
    }
}
