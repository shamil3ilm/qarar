<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleMenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id', 'module_id', 'menu_label', 'menu_icon', 'route_name',
        'parent_menu', 'position', 'is_visible', 'is_pinned',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'is_pinned' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleDefinition::class, 'module_id');
    }
}
