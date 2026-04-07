<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class MmInvoiceToleranceRule extends Model
{
    use BelongsToOrganization, HasUuid;

    public const TYPE_PRICE = 'price';
    public const TYPE_QUANTITY = 'quantity';
    public const TYPE_DATE = 'date';
    public const TYPE_AMOUNT = 'amount';

    public const OPERATOR_ABSOLUTE = 'absolute';
    public const OPERATOR_PERCENTAGE = 'percentage';

    protected $fillable = [
        'organization_id',
        'rule_name',
        'tolerance_type',
        'comparison_operator',
        'lower_tolerance',
        'upper_tolerance',
        'auto_block',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'lower_tolerance' => 'decimal:4',
            'upper_tolerance' => 'decimal:4',
            'auto_block' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
