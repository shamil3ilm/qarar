<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafetyDataSheet extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'safety_data_sheets';

    protected $fillable = [
        'organization_id',
        'product_id',
        'sds_number',
        'version',
        'revision_date',
        'language_code',
        'supplier_name',
        'emergency_phone',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'revision_date' => 'date',
            'is_current'    => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SafetyDataSheetSection::class)->orderBy('section_number');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeForLanguage(Builder $query, string $languageCode): Builder
    {
        return $query->where('language_code', $languageCode);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Mark all other SDS records for the same product/language as non-current
     * before saving this record as current.
     */
    public function markAsCurrentVersion(): void
    {
        static::withoutGlobalScope('organization')
            ->where('organization_id', $this->organization_id)
            ->where('product_id', $this->product_id)
            ->where('language_code', $this->language_code)
            ->where('id', '!=', $this->id)
            ->update(['is_current' => false]);

        $this->update(['is_current' => true]);
    }
}
