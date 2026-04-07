<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PlatformPermission extends Model
{
    use HasFactory;
    public const MODULE_ORGANIZATIONS = 'organizations';
    public const MODULE_USERS = 'users';
    public const MODULE_BILLING = 'billing';
    public const MODULE_SUPPORT = 'support';
    public const MODULE_SYSTEM = 'system';

    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}
