<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PlatformAdminActivity extends Model
{
    use HasFactory;
    protected $fillable = [
        'admin_id',
        'action',
        'entity_type',
        'entity_id',
        'organization_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeForEntity($query, string $entityType, ?int $entityId = null)
    {
        $query->where('entity_type', $entityType);
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        return $query;
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
