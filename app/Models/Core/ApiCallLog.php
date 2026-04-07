<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCallLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'organization_id',
        'service',
        'method',
        'url',
        'request_headers',
        'request_body',
        'response_status',
        'response_body',
        'duration_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body'    => 'array',
        'response_body'   => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\Organization::class);
    }

    public function scopeErrors($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }
}
