<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TdsDeduction extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date'    => 'date',
            'payment_amount'  => 'decimal:4',
            'tds_rate'        => 'decimal:2',
            'tds_amount'      => 'decimal:4',
            'surcharge'       => 'decimal:4',
            'education_cess'  => 'decimal:4',
            'net_tds'         => 'decimal:4',
            'deposited_at'    => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TdsSection::class, 'section_id');
    }

    public function isDeposited(): bool
    {
        return $this->deposited_at !== null;
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForQuarter($query, int $quarter, int $year)
    {
        return $query->where('period_quarter', $quarter)->where('period_year', $year);
    }

    public function scopeUndeposited($query)
    {
        return $query->whereNull('deposited_at');
    }
}
