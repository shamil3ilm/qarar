<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutlineAgreementItem extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'outline_agreement_id',
        'product_id',
        'line_number',
        'description',
        'target_quantity',
        'target_value',
        'released_quantity',
        'released_value',
        'unit_price',
        'unit_of_measure',
    ];

    protected function casts(): array
    {
        return [
            'target_quantity'   => 'decimal:4',
            'target_value'      => 'decimal:4',
            'released_quantity' => 'decimal:4',
            'released_value'    => 'decimal:4',
            'unit_price'        => 'decimal:4',
        ];
    }

    // Relationships

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(OutlineAgreement::class, 'outline_agreement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(OutlineAgreementRelease::class);
    }

    // Helpers

    public function getRemainingQuantity(): ?string
    {
        if ($this->target_quantity === null) {
            return null;
        }

        return bcsub((string) $this->target_quantity, (string) $this->released_quantity, 4);
    }

    public function getRemainingValue(): ?string
    {
        if ($this->target_value === null) {
            return null;
        }

        return bcsub((string) $this->target_value, (string) $this->released_value, 4);
    }
}
