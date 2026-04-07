<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FunctionalLocation extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_PLANT     = 'plant';
    public const TYPE_AREA      = 'area';
    public const TYPE_LINE      = 'line';
    public const TYPE_MACHINE   = 'machine';
    public const TYPE_COMPONENT = 'component';

    public const LOCATION_TYPES = [
        self::TYPE_PLANT,
        self::TYPE_AREA,
        self::TYPE_LINE,
        self::TYPE_MACHINE,
        self::TYPE_COMPONENT,
    ];

    protected $fillable = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'description',
        'location_type',
        'branch_id',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Relations

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FunctionalLocation::class, 'parent_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'functional_location_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('location_type', $type);
    }
}
