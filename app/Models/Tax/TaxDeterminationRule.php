<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxDeterminationRule extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    // Document types
    public const DOCUMENT_TYPE_SALES_INVOICE   = 'sales_invoice';
    public const DOCUMENT_TYPE_PURCHASE_BILL   = 'purchase_bill';
    public const DOCUMENT_TYPE_SALES_ORDER     = 'sales_order';
    public const DOCUMENT_TYPE_PURCHASE_ORDER  = 'purchase_order';
    public const DOCUMENT_TYPE_ALL             = 'all';

    // Customer types
    public const CUSTOMER_TYPE_B2B        = 'b2b';
    public const CUSTOMER_TYPE_B2C        = 'b2c';
    public const CUSTOMER_TYPE_GOVERNMENT  = 'government';
    public const CUSTOMER_TYPE_EXEMPT      = 'exempt';
    public const CUSTOMER_TYPE_ANY         = 'any';

    // Tax types
    public const TAX_TYPE_STANDARD       = 'standard';
    public const TAX_TYPE_ZERO           = 'zero';
    public const TAX_TYPE_EXEMPT         = 'exempt';
    public const TAX_TYPE_REVERSE_CHARGE = 'reverse_charge';
    public const TAX_TYPE_OUT_OF_SCOPE   = 'out_of_scope';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'document_type',
        'from_country_code',
        'to_country_code',
        'from_region',
        'to_region',
        'tax_category_id',
        'customer_type',
        'tax_type',
        'tax_rate_id',
        'is_reverse_charge',
        'priority',
        'is_active',
        'valid_from',
        'valid_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_reverse_charge' => 'boolean',
            'is_active'         => 'boolean',
            'priority'          => 'integer',
            'valid_from'        => 'date',
            'valid_to'          => 'date',
        ];
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Only active rules that are within their validity period (if any).
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });
    }

    /**
     * Filter by document type (also includes rules that apply to 'all').
     */
    public function scopeForDocument(Builder $query, string $type): Builder
    {
        return $query->where(function (Builder $q) use ($type): void {
            $q->where('document_type', $type)
              ->orWhere('document_type', self::DOCUMENT_TYPE_ALL);
        });
    }
}
