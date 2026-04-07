<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformAdmin extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    protected $casts = [
        'is_active'                  => 'boolean',
        'is_2fa_enabled'             => 'boolean',
        'permissions'                => 'array',
        'two_factor_recovery_codes'  => 'array',
        'last_login_at'              => 'datetime',
    ];
}