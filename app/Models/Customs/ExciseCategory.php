<?php

declare(strict_types=1);

namespace App\Models\Customs;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExciseCategory extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function rates(): HasMany
    {
        return $this->hasMany(ExciseRate::class, 'excise_category_id');
    }

    public function productMappings(): HasMany
    {
        return $this->hasMany(ProductExciseMapping::class, 'excise_category_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getCurrentRate(?string $countryCode = null, ?string $date = null): ?ExciseRate
    {
        $date = $date ?? now()->toDateString();

        $query = $this->rates()
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from');

        if ($countryCode) {
            $query->where('country_code', $countryCode);
        }

        return $query->first();
    }
}
