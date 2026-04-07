<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class OrganizationStatusHistory extends Model
{
    use HasFactory;
    protected $table = 'organization_status_history';

    protected $fillable = [
        'organization_id',
        'admin_id',
        'status_from',
        'status_to',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
