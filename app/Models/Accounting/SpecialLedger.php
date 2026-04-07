<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecialLedger extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'accounting_principle',
        'is_leading',
        'is_active',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'is_leading' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SpecialLedgerEntry::class);
    }

    public function mappingRules(): HasMany
    {
        return $this->hasMany(SpecialLedgerMappingRule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
