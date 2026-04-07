<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeTransportObject extends Model
{
    use HasUuid;

    public const TYPE_MIGRATION  = 'migration';
    public const TYPE_CONFIG     = 'config';
    public const TYPE_ROUTE      = 'route';
    public const TYPE_PERMISSION = 'permission';
    public const TYPE_SETTING    = 'setting';

    public const CHANGE_CREATE = 'create';
    public const CHANGE_MODIFY = 'modify';
    public const CHANGE_DELETE = 'delete';

    protected $fillable = [
        'uuid',
        'change_transport_request_id',
        'object_type',
        'object_name',
        'object_key',
        'change_type',
        'payload',
        'checksums',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ChangeTransportRequest::class, 'change_transport_request_id');
    }
}
