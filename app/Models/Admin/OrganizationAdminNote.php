<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class OrganizationAdminNote extends Model
{
    use HasFactory;
    public const TYPE_GENERAL = 'general';
    public const TYPE_WARNING = 'warning';
    public const TYPE_SUPPORT = 'support';
    public const TYPE_BILLING = 'billing';

    protected $fillable = [
        'organization_id',
        'admin_id',
        'note',
        'note_type',
        'is_internal',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_pinned' => 'boolean',
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

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }
}
