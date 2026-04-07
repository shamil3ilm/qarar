<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeTransportLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATED        = 'created';
    public const ACTION_OBJECT_ADDED   = 'object_added';
    public const ACTION_RELEASED       = 'released';
    public const ACTION_IMPORT_STARTED = 'import_started';
    public const ACTION_IMPORTED       = 'imported';
    public const ACTION_FAILED         = 'failed';
    public const ACTION_ROLLBACK       = 'rollback';

    protected $fillable = [
        'change_transport_request_id',
        'action',
        'performed_by',
        'environment',
        'message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ChangeTransportRequest::class, 'change_transport_request_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
