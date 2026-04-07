<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationModuleAccess extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'organization_module_access';

    protected $fillable = [
        'organization_id', 'module_id', 'is_enabled', 'enabled_at', 'disabled_at',
        'enabled_by', 'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled_at' => 'date',
        'disabled_at' => 'date',
        'config' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleDefinition::class, 'module_id');
    }

    public function enabledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}
