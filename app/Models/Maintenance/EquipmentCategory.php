<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentCategory extends Model
{
    use BelongsToOrganization;

    protected $table = 'equipment_categories';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
    ];

    // Relations

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'equipment_category_id');
    }
}
