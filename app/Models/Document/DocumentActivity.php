<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class DocumentActivity extends Model
{
    use HasFactory;
    // Action types
    public const ACTION_VIEWED = 'viewed';
    public const ACTION_DOWNLOADED = 'downloaded';
    public const ACTION_UPLOADED = 'uploaded';
    public const ACTION_EDITED = 'edited';
    public const ACTION_SHARED = 'shared';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_MOVED = 'moved';
    public const ACTION_SIGNED = 'signed';
    public const ACTION_VERSION_CREATED = 'version_created';

    public const ACTIONS = [
        self::ACTION_VIEWED,
        self::ACTION_DOWNLOADED,
        self::ACTION_UPLOADED,
        self::ACTION_EDITED,
        self::ACTION_SHARED,
        self::ACTION_DELETED,
        self::ACTION_RESTORED,
        self::ACTION_MOVED,
        self::ACTION_SIGNED,
        self::ACTION_VERSION_CREATED,
    ];

    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // Relationships

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForDocument($query, int $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
