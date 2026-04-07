<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PlatformAdminRole extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}
