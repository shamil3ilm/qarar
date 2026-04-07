<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    public const TYPE_PERSONAL = 'personal';
    public const TYPE_TEAM = 'team';
    public const TYPE_ORGANIZATION = 'organization';
    public const TYPE_RESOURCE = 'resource';

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'color',
        'description',
        'type',
        'is_default',
        'is_visible',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function scopePersonal($query)
    {
        return $query->where('type', self::TYPE_PERSONAL);
    }

    public function scopeTeam($query)
    {
        return $query->where('type', self::TYPE_TEAM);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isPersonal(): bool
    {
        return $this->type === self::TYPE_PERSONAL;
    }

    public function isTeam(): bool
    {
        return $this->type === self::TYPE_TEAM;
    }

    public function isOrganizationCalendar(): bool
    {
        return $this->type === self::TYPE_ORGANIZATION;
    }

    public function isResource(): bool
    {
        return $this->type === self::TYPE_RESOURCE;
    }
}
