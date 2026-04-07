<?php

declare(strict_types=1);

namespace App\Models\Customs;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CustomsDeclarationItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'declaration_id',
        'product_id',
        'variant_id',
        'item_number',
        'description',
        'tariff_code',
        'tariff_id',
        'quantity',
        'unit',
        'gross_weight_kg',
        'net_weight_kg',
        'unit_value',
        'total_value',
        'assessable_value',
        'duty_rate',
        'duty_amount',
        'vat_rate',
        'vat_amount',
        'excise_rate',
        'excise_amount',
        'cess_rate',
        'cess_amount',
        'other_charges',
        'total_taxes',
        'country_of_origin',
        'preferential_tariff_code',
        'preferential_treatment',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'gross_weight_kg' => 'decimal:4',
            'net_weight_kg' => 'decimal:4',
            'unit_value' => 'decimal:4',
            'total_value' => 'decimal:4',
            'assessable_value' => 'decimal:4',
            'duty_rate' => 'decimal:4',
            'duty_amount' => 'decimal:4',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:4',
            'excise_rate' => 'decimal:4',
            'excise_amount' => 'decimal:4',
            'cess_rate' => 'decimal:4',
            'cess_amount' => 'decimal:4',
            'other_charges' => 'decimal:4',
            'total_taxes' => 'decimal:4',
            'item_number' => 'integer',
            'preferential_treatment' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(CustomsDeclaration::class, 'declaration_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(CustomsTariffCode::class, 'tariff_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForTariffCode(Builder $query, string $tariffCode): Builder
    {
        return $query->where('tariff_code', $tariffCode);
    }

    public function scopeWithPreferentialTreatment(Builder $query): Builder
    {
        return $query->where('preferential_treatment', true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function calculateTaxes(): void
    {
        $assessable = (float) ($this->assessable_value ?: $this->total_value);
        $this->duty_amount = round($assessable * ((float) $this->duty_rate / 100), 4);

        $valueAfterDuty = $assessable + (float) $this->duty_amount;
        $this->excise_amount = round($valueAfterDuty * ((float) $this->excise_rate / 100), 4);

        $valueAfterExcise = $valueAfterDuty + (float) $this->excise_amount;
        $this->vat_amount = round($valueAfterExcise * ((float) $this->vat_rate / 100), 4);

        $this->cess_amount = round($assessable * ((float) $this->cess_rate / 100), 4);

        $this->total_taxes = (float) $this->duty_amount
            + (float) $this->vat_amount
            + (float) $this->excise_amount
            + (float) $this->cess_amount
            + (float) $this->other_charges;
    }
}
