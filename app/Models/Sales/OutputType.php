<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutputType extends Model
{
    use BelongsToOrganization, HasFactory;

    public const MEDIUM_PRINT  = 'print';
    public const MEDIUM_EMAIL  = 'email';
    public const MEDIUM_EDI    = 'edi';
    public const MEDIUM_PORTAL = 'portal';

    public const DISPATCH_IMMEDIATELY = 'immediately';
    public const DISPATCH_ON_SAVE     = 'on_save';
    public const DISPATCH_ON_POST     = 'on_post';
    public const DISPATCH_SCHEDULED   = 'scheduled';

    public const DOC_INVOICE        = 'invoice';
    public const DOC_SALES_ORDER    = 'sales_order';
    public const DOC_QUOTATION      = 'quotation';
    public const DOC_DELIVERY_NOTE  = 'delivery_note';
    public const DOC_PURCHASE_ORDER = 'purchase_order';
    public const DOC_PAYMENT        = 'payment';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'document_type',
        'output_medium',
        'email_template',
        'print_template',
        'dispatch_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function conditionRecords(): HasMany
    {
        return $this->hasMany(OutputConditionRecord::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutputMessage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    public function scopeForDispatchTime($query, string $dispatchTime)
    {
        return $query->where('dispatch_time', $dispatchTime);
    }
}
