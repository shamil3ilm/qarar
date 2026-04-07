<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItcLedger extends Model
{
    use HasFactory;

    protected $table = 'itc_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'igst_available' => 'decimal:4',
            'cgst_available' => 'decimal:4',
            'sgst_available' => 'decimal:4',
            'igst_utilized'  => 'decimal:4',
            'cgst_utilized'  => 'decimal:4',
            'sgst_utilized'  => 'decimal:4',
            'igst_closing'   => 'decimal:4',
            'cgst_closing'   => 'decimal:4',
            'sgst_closing'   => 'decimal:4',
        ];
    }

    public function gstRegistration(): BelongsTo
    {
        return $this->belongsTo(GstRegistration::class, 'gstin_id');
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForPeriod($query, int $month, int $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }

    /**
     * Total available ITC across all components.
     */
    public function getTotalAvailableAttribute(): float
    {
        return (float) bcadd(
            bcadd((string) $this->igst_available, (string) $this->cgst_available, 4),
            (string) $this->sgst_available,
            4
        );
    }

    /**
     * Total utilized ITC across all components.
     */
    public function getTotalUtilizedAttribute(): float
    {
        return (float) bcadd(
            bcadd((string) $this->igst_utilized, (string) $this->cgst_utilized, 4),
            (string) $this->sgst_utilized,
            4
        );
    }
}
