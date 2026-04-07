<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log for sensitive model reads.
 * No soft-deletes — audit records must not be deletable through the app.
 */
class SensitiveAccessLog extends Model
{
    /** Disable update timestamps since rows are insert-only. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'user_id',
        'model_type',
        'model_id',
        'action',
        'sensitive_fields',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'model_id' => 'integer',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
