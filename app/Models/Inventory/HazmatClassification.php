<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HazmatClassification extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    public const SYSTEM_GHS  = 'ghs';
    public const SYSTEM_UN   = 'un';
    public const SYSTEM_ADR  = 'adr';
    public const SYSTEM_IATA = 'iata';

    public const SIGNAL_DANGER  = 'danger';
    public const SIGNAL_WARNING = 'warning';

    protected $table = 'hazmat_classifications';

    protected $fillable = [
        'organization_id',
        'classification_system',
        'code',
        'name',
        'hazard_class',
        'packing_group',
        'signal_word',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_hazmat_classifications',
            'hazmat_classification_id',
            'product_id'
        )->withPivot(['storage_class_id', 'is_primary'])->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySystem(Builder $query, string $system): Builder
    {
        return $query->where('classification_system', $system);
    }
}
