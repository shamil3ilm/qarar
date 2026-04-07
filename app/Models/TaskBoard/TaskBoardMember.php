<?php

declare(strict_types=1);

namespace App\Models\TaskBoard;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaskBoardMember extends Model
{
    use HasFactory;
    protected $table = 'task_board_members';

    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'board_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    // Relationships

    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeOwners($query)
    {
        return $query->where('role', self::ROLE_OWNER);
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', [self::ROLE_OWNER, self::ROLE_ADMIN]);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helpers

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function isMember(): bool
    {
        return $this->role === self::ROLE_MEMBER;
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    public function canEdit(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MEMBER], true);
    }

    public function canManage(): bool
    {
        return $this->isAdmin();
    }
}
