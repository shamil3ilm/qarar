<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GstRegistration extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'is_active'         => 'boolean',
        ];
    }

    public function gstr1Returns(): HasMany
    {
        return $this->hasMany(Gstr1Return::class, 'gstin_id');
    }

    public function gstr3bReturns(): HasMany
    {
        return $this->hasMany(Gstr3bReturn::class, 'gstin_id');
    }

    public function itcLedgerEntries(): HasMany
    {
        return $this->hasMany(ItcLedger::class, 'gstin_id');
    }

    public function ewaybills(): HasMany
    {
        return $this->hasMany(Ewaybill::class, 'organization_id', 'organization_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Extract state code from GSTIN (first 2 characters).
     */
    public function getStateCodeFromGstin(): string
    {
        return substr($this->gstin, 0, 2);
    }
}
