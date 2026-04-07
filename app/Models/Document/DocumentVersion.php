<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class DocumentVersion extends Model
{
    use HasFactory;
    protected $fillable = [
        'document_id',
        'version_number',
        'file_path',
        'file_size',
        'mime_type',
        'change_summary',
        'change_notes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size' => 'integer',
        ];
    }

    // Relationships

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scopes

    public function scopeLatest($query)
    {
        return $query->orderByDesc('version_number');
    }

    public function scopeForDocument($query, int $documentId)
    {
        return $query->where('document_id', $documentId);
    }
}
