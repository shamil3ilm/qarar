<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HazmatStorageClass extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'hazmat_storage_classes';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'max_quantity_kg',
        'requires_ventilation',
        'requires_grounding',
        'fire_resistance_class',
    ];

    protected function casts(): array
    {
        return [
            'max_quantity_kg'      => 'decimal:2',
            'requires_ventilation' => 'boolean',
            'requires_grounding'   => 'boolean',
        ];
    }

    public function compatibilityRulesAsA(): HasMany
    {
        return $this->hasMany(HazmatStorageCompatibilityRule::class, 'storage_class_a_id');
    }

    public function compatibilityRulesAsB(): HasMany
    {
        return $this->hasMany(HazmatStorageCompatibilityRule::class, 'storage_class_b_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_hazmat_classifications',
            'storage_class_id',
            'product_id'
        )->withPivot(['hazmat_classification_id', 'is_primary'])->withTimestamps();
    }
}
