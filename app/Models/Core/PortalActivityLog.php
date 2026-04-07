<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalActivityLog extends Model
{
    use HasFactory;

    protected $table = 'portal_activity_logs';

    /**
     * This is an append-only log — no updated_at column.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'portal_user_id',
        'activity_type',
        'description',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }
}
