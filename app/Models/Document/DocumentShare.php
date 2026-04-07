<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentShare extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    // Share type constants
    public const TYPE_EMAIL = 'email';
    public const TYPE_LINK = 'link';
    public const TYPE_USER = 'user';

    public const SHARE_TYPES = [
        self::TYPE_EMAIL,
        self::TYPE_LINK,
        self::TYPE_USER,
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function document(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Document\Document::class);
    }
}