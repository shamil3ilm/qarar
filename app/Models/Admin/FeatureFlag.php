<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_enabled',
        'rollout_type',
        'rollout_percentage',
        'specific_organization_ids',
        'specific_subscription_plans',
        'config',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'is_enabled'                => 'boolean',
        'specific_organization_ids' => 'array',
        'specific_subscription_plans' => 'array',
        'config'                    => 'array',
        'starts_at'                 => 'datetime',
        'ends_at'                   => 'datetime',
        'enabled_at'                => 'datetime',
        'disabled_at'               => 'datetime',
    ];
}