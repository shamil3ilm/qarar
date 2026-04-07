<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalDocumentAccess extends Model
{
    use HasFactory;

    protected $table = 'portal_document_accesses';

    public const TYPE_INVOICE     = 'invoice';
    public const TYPE_QUOTATION   = 'quotation';
    public const TYPE_ORDER       = 'order';
    public const TYPE_CREDIT_NOTE = 'credit_note';
    public const TYPE_STATEMENT   = 'statement';

    protected $fillable = [
        'organization_id',
        'portal_user_id',
        'document_type',
        'document_id',
        'accessed_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
            'document_id' => 'integer',
        ];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }
}
