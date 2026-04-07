<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasUuid;

class VatTransaction extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tax_period'      => 'date',
            'taxable_amount'  => 'decimal:4',
            'vat_amount'      => 'decimal:4',
            'vat_rate'        => 'decimal:2',
            'is_exempt'       => 'boolean',
            'is_zero_rated'   => 'boolean',
        ];
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('tax_period', [$start, $end]);
    }

    public function scopeOutputTax($query)
    {
        return $query->where('transaction_type', 'sale');
    }

    public function scopeInputTax($query)
    {
        return $query->where('transaction_type', 'purchase');
    }
}
