<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFolder extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;

    protected $guarded = ['id'];

    // Access level values (canonical names)
    public const ACCESS_ORGANIZATION = 'organization';
    public const ACCESS_BRANCH = 'branch';
    public const ACCESS_PRIVATE = 'private';

    // Aliases kept for backward compatibility
    public const ACCESS_LEVEL_ORGANIZATION = self::ACCESS_ORGANIZATION;
    public const ACCESS_LEVEL_BRANCH = self::ACCESS_BRANCH;
    public const ACCESS_LEVEL_PRIVATE = self::ACCESS_PRIVATE;

    public const ACCESS_LEVELS = [
        self::ACCESS_ORGANIZATION,
        self::ACCESS_BRANCH,
        self::ACCESS_PRIVATE,
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DocumentFolder::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}