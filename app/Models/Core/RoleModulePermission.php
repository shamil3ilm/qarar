<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleModulePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id', 'module_id', 'can_view', 'can_create', 'can_edit', 'can_delete',
        'can_export', 'can_import', 'can_approve', 'can_print', 'data_scope',
        'max_amount_limit', 'max_discount_percent', 'custom_permissions',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_export' => 'boolean',
        'can_import' => 'boolean',
        'can_approve' => 'boolean',
        'can_print' => 'boolean',
        'max_amount_limit' => 'decimal:2',
        'max_discount_percent' => 'decimal:2',
        'custom_permissions' => 'array',
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
