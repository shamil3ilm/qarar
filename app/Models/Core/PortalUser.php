<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class PortalUser extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes, Notifiable;

    protected $table = 'portal_users';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'email',
        'password_hash',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'login_count',
        'password_reset_token',
        'password_reset_expires_at',
    ];

    protected $hidden = [
        'password_hash',
        'password_reset_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                 => 'boolean',
            'email_verified_at'         => 'datetime',
            'last_login_at'             => 'datetime',
            'login_count'               => 'integer',
            'password_reset_expires_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PortalSession::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(PortalActivityLog::class);
    }

    public function documentAccesses(): HasMany
    {
        return $this->hasMany(PortalDocumentAccess::class);
    }

    public function isPasswordResetTokenValid(): bool
    {
        return $this->password_reset_token !== null
            && $this->password_reset_expires_at !== null
            && $this->password_reset_expires_at->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
