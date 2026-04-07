<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'module_id', 'override_type', 'can_view', 'can_create', 'can_edit',
        'can_delete', 'can_export', 'can_import', 'can_approve', 'data_scope',
        'max_amount_limit', 'custom_permissions', 'reason', 'expires_at', 'granted_by',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_export' => 'boolean',
        'can_import' => 'boolean',
        'can_approve' => 'boolean',
        'max_amount_limit' => 'decimal:2',
        'custom_permissions' => 'array',
        'expires_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleDefinition::class, 'module_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
