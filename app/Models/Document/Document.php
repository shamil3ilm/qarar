<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    // Document types
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_ID_PROOF = 'id_proof';
    public const TYPE_REPORT = 'report';
    public const TYPE_CERTIFICATE = 'certificate';
    public const TYPE_LETTER = 'letter';
    public const TYPE_OTHER = 'other';

    public const DOCUMENT_TYPES = [
        self::TYPE_CONTRACT,
        self::TYPE_INVOICE,
        self::TYPE_RECEIPT,
        self::TYPE_ID_PROOF,
        self::TYPE_REPORT,
        self::TYPE_CERTIFICATE,
        self::TYPE_LETTER,
        self::TYPE_OTHER,
    ];

    // Access levels
    public const ACCESS_ORGANIZATION = 'organization';
    public const ACCESS_BRANCH = 'branch';
    public const ACCESS_PRIVATE = 'private';

    public const ACCESS_LEVELS = [
        self::ACCESS_ORGANIZATION,
        self::ACCESS_BRANCH,
        self::ACCESS_PRIVATE,
    ];

    protected $fillable = [
        'organization_id',
        'folder_id',
        'name',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'extension',
        'description',
        'tags',
        'document_type',
        'document_date',
        'expiry_date',
        'is_expiry_notified',
        'documentable_type',
        'documentable_id',
        'access_level',
        'is_archived',
        'uploaded_by',
        'download_count',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'file_size' => 'integer',
            'download_count' => 'integer',
            'is_expiry_notified' => 'boolean',
            'is_archived' => 'boolean',
            'document_date' => 'date',
            'expiry_date' => 'date',
            'last_accessed_at' => 'datetime',
        ];
    }

    // Relationships

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(DocumentPermission::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DigitalSignature::class);
    }

    // Scopes

    public function scopeInFolder($query, ?int $folderId)
    {
        return $folderId
            ? $query->where('folder_id', $folderId)
            : $query->whereNull('folder_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('file_name', 'like', "%{$term}%");
        });
    }

    public function scopeForEntity($query, string $entityType, $entityId)
    {
        return $query->where('documentable_type', $entityType)
            ->where('documentable_id', $entityId);
    }

    // Helpers

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date
            && $this->expiry_date->isFuture()
            && $this->expiry_date->diffInDays(now()) <= $days;
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function getLatestVersion(): ?DocumentVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }

    public function getNextVersionNumber(): int
    {
        return ($this->versions()->max('version_number') ?? 0) + 1;
    }
}
