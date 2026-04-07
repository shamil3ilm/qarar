<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminIpWhitelist extends Model
{
    use HasFactory;

    protected $table = 'admin_ip_whitelist';

    protected $guarded = ['id'];
}