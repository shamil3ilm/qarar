<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeTransportObjectAssignment extends Model
{
    protected $fillable = [
        'change_transport_request_id',
        'user_id',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ChangeTransportRequest::class, 'change_transport_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
