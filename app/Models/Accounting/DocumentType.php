<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'accounting_document_types';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'account_type',
        'number_range_code',
        'reverse_document_type',
        'reverse_document_type_code',
        'require_reference',
        'check_duplicate_invoice',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reverse_document_type'   => 'boolean',
            'require_reference'       => 'boolean',
            'check_duplicate_invoice' => 'boolean',
            'is_active'               => 'boolean',
        ];
    }

    public const STANDARD_TYPES = [
        'SA' => 'G/L Account Document',
        'KR' => 'Vendor Invoice',
        'KZ' => 'Vendor Payment',
        'DR' => 'Customer Invoice',
        'DZ' => 'Customer Payment',
        'AB' => 'Accounting Document',
        'WA' => 'Goods Issue',
        'WE' => 'Goods Receipt',
        'RE' => 'Invoice Receipt',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
