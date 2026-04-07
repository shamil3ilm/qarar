<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrCodeConfig extends Model
{
    use BelongsToOrganization, HasFactory;

    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_INVOICE = 'invoice';
    public const ENTITY_RECEIPT = 'receipt';
    public const ENTITY_PRICE_TAG = 'price_tag';
    public const ENTITY_SHELF_LABEL = 'shelf_label';

    public const CONTENT_URL = 'url';
    public const CONTENT_JSON = 'json';
    public const CONTENT_VCARD = 'vcard';
    public const CONTENT_TEXT = 'text';
    public const CONTENT_CUSTOM = 'custom';

    public const ERROR_CORRECTION_LOW = 'L';
    public const ERROR_CORRECTION_MEDIUM = 'M';
    public const ERROR_CORRECTION_QUARTILE = 'Q';
    public const ERROR_CORRECTION_HIGH = 'H';

    protected $fillable = [
        'organization_id',
        'entity_type',
        'name',
        'content_type',
        'content_template',
        'included_fields',
        'size_px',
        'foreground_color',
        'background_color',
        'logo_path',
        'error_correction',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'included_fields' => 'array',
            'size_px' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeByContentType($query, string $contentType)
    {
        return $query->where('content_type', $contentType);
    }
}
