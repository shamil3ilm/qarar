<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CustomFieldGroup extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'entity_type',
        'name',
        'slug',
        'description',
        'display_order',
        'is_collapsible',
        'is_collapsed_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_collapsible' => 'boolean',
            'is_collapsed_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function fields(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class, 'field_group', 'slug')
            ->where('entity_type', $this->entity_type)
            ->where('organization_id', $this->organization_id);
    }

    // Scopes

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
