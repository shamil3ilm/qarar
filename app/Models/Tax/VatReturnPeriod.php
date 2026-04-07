<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VatReturnPeriod extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start'   => 'date',
            'period_end'     => 'date',
            'submitted_at'   => 'datetime',
        ];
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(VatReturnBox::class, 'vat_return_period_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VatTransaction::class, 'vat_return_period_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'accepted'], true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
