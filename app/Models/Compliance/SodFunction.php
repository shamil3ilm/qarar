<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SodFunction extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'grc_sod_functions';

    protected $fillable = [
        'organization_id',
        'function_code',
        'name',
        'module',
        'description',
        'permissions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active'   => 'boolean',
        ];
    }

    public function conflictsAsA(): HasMany
    {
        return $this->hasMany(SodConflict::class, 'function_a_id');
    }

    public function conflictsAsB(): HasMany
    {
        return $this->hasMany(SodConflict::class, 'function_b_id');
    }
}
