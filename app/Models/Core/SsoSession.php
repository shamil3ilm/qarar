<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SsoSession extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'uuid', 'organization_id', 'user_id', 'provider_id', 'external_user_id',
        'access_token_hash', 'id_token_hash', 'session_started_at', 'last_activity_at', 'expires_at',
    ];

    protected $casts = [
        'session_started_at' => 'datetime',
        'last_activity_at'   => 'datetime',
        'expires_at'         => 'datetime',
    ];

    protected $hidden = ['access_token_hash', 'id_token_hash'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function provider(): BelongsTo { return $this->belongsTo(SsoProvider::class, 'provider_id'); }
}
