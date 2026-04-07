<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingConditionType extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'condition_class',
        'calculation_type',
        'is_mandatory',
        'step',
        'counter',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'step' => 'integer',
            'counter' => 'integer',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(PricingConditionRecord::class, 'condition_type_id');
    }

    public function isPrice(): bool
    {
        return $this->condition_class === 'price';
    }

    public function isDiscount(): bool
    {
        return $this->condition_class === 'discount';
    }

    public function isTax(): bool
    {
        return $this->condition_class === 'tax';
    }

    public function scopeByClass($query, string $class)
    {
        return $query->where('condition_class', $class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('step')->orderBy('counter');
    }
}
