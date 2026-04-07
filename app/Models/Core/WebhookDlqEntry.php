<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDlqEntry extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'uuid', 'organization_id', 'webhook_id', 'event_type', 'payload',
        'failure_count', 'first_failed_at', 'last_failed_at', 'last_error',
        'next_retry_at', 'status', 'replayed_at', 'replayed_by',
    ];

    protected $casts = [
        'payload'        => 'array',
        'first_failed_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'next_retry_at'  => 'datetime',
        'replayed_at'    => 'datetime',
    ];

    public function replayedBy(): BelongsTo { return $this->belongsTo(User::class, 'replayed_by'); }
}
