<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class Conversation extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'subject',
        'type',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->latest()->limit(1);
    }

    public function isParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function markAsRead(int $userId): void
    {
        $this->participants()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);
    }
}
