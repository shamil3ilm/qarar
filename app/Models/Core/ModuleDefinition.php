<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'group', 'description', 'icon', 'sub_modules',
        'required_modules', 'min_subscription_tier', 'is_core', 'is_active', 'display_order',
    ];

    protected $casts = [
        'sub_modules' => 'array',
        'required_modules' => 'array',
        'is_core' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function organizationAccess(): HasMany
    {
        return $this->hasMany(OrganizationModuleAccess::class, 'module_id');
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RoleModulePermission::class, 'module_id');
    }
}
