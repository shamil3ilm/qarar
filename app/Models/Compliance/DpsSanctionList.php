<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DpsSanctionList extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'dps_sanction_lists';

    public const AUTHORITY_OFAC  = 'OFAC';
    public const AUTHORITY_EU    = 'EU';
    public const AUTHORITY_UN    = 'UN';
    public const AUTHORITY_HMT   = 'HMT';
    public const AUTHORITY_LOCAL = 'local';
    public const AUTHORITY_OTHER = 'other';

    public const TYPE_DENIED_PARTY = 'denied_party';
    public const TYPE_EMBARGO      = 'embargo';
    public const TYPE_DEBARRED     = 'debarred';

    protected $fillable = [
        'organization_id',
        'list_name',
        'list_authority',
        'list_type',
        'last_updated_at',
        'entry_count',
        'is_active',
        'auto_sync',
        'sync_url',
    ];

    protected function casts(): array
    {
        return [
            'last_updated_at' => 'datetime',
            'entry_count'     => 'integer',
            'is_active'       => 'boolean',
            'auto_sync'       => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DpsListEntry::class);
    }

    public function activeEntries(): HasMany
    {
        return $this->hasMany(DpsListEntry::class)->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
