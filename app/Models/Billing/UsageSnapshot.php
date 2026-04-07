<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageSnapshot extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'users_count', 'branches_count', 'storage_used_mb',
        'invoices_this_month', 'products_count', 'customers_count',
        'employees_count', 'api_calls_this_month', 'snapshot_at',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
    ];
}
