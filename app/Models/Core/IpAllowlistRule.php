<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpAllowlistRule extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'rule_name', 'ip_address', 'ip_range_start',
        'ip_range_end', 'cidr_notation', 'rule_type', 'applies_to',
        'role_id', 'active', 'created_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
