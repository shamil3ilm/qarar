<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gstr1Return extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'gstr1_returns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_taxable_value' => 'decimal:4',
            'total_igst'          => 'decimal:4',
            'total_cgst'          => 'decimal:4',
            'total_sgst'          => 'decimal:4',
            'total_cess'          => 'decimal:4',
            'filed_at'            => 'datetime',
        ];
    }

    public function gstRegistration(): BelongsTo
    {
        return $this->belongsTo(GstRegistration::class, 'gstin_id');
    }

    public function b2bInvoices(): HasMany
    {
        return $this->hasMany(Gstr1B2bInvoice::class, 'gstr1_return_id');
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
