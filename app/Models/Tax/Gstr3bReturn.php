<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gstr3bReturn extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'gstr3b_returns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'outward_taxable_supplies' => 'decimal:4',
            'outward_zero_rated'       => 'decimal:4',
            'inward_supplies_itc'      => 'decimal:4',
            'net_tax_payable'          => 'decimal:4',
            'filed_at'                 => 'datetime',
        ];
    }

    public function gstRegistration(): BelongsTo
    {
        return $this->belongsTo(GstRegistration::class, 'gstin_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFiled(): bool
    {
        return $this->status === 'filed';
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForPeriod($query, int $month, int $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }
}
