<?php

declare(strict_types=1);

namespace App\Models\CRM;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerritoryRoutingRule extends Model
{
    use HasFactory, HasUuid;

    // Entity type constants
    public const ENTITY_LEAD        = 'lead';
    public const ENTITY_OPPORTUNITY = 'opportunity';
    public const ENTITY_CONTACT     = 'contact';

    // Match field constants
    public const FIELD_COUNTRY     = 'country';
    public const FIELD_STATE       = 'state';
    public const FIELD_POSTAL_CODE = 'postal_code';
    public const FIELD_CITY        = 'city';
    public const FIELD_CUSTOM      = 'custom';

    protected $fillable = [
        'organization_id',
        'territory_id',
        'entity_type',
        'match_field',
        'match_value',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority'  => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * Determine whether the given entity data satisfies this rule.
     */
    public function matchesEntity(array $entityData): bool
    {
        $fieldMap = [
            self::FIELD_COUNTRY     => 'country_code',
            self::FIELD_STATE       => 'state',
            self::FIELD_POSTAL_CODE => 'postal_code',
            self::FIELD_CITY        => 'city',
            self::FIELD_CUSTOM      => 'custom_value',
        ];

        $dataKey = $fieldMap[$this->match_field] ?? null;
        if ($dataKey === null) {
            return false;
        }

        $dataValue = $entityData[$dataKey] ?? null;
        if ($dataValue === null) {
            return false;
        }

        // Support wildcard suffix patterns like "SA*" matching "SAR"
        if (str_ends_with($this->match_value, '*')) {
            $prefix = rtrim($this->match_value, '*');
            return str_starts_with((string) $dataValue, $prefix);
        }

        return strtolower((string) $dataValue) === strtolower($this->match_value);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
