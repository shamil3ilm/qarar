<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeTransportRequest extends Model
{
    use HasUuid;
    use SoftDeletes;

    public const STATUS_OPEN     = 'open';
    public const STATUS_RELEASED = 'released';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED   = 'failed';

    public const TYPE_WORKBENCH           = 'workbench';
    public const TYPE_CUSTOMIZING         = 'customizing';
    public const TYPE_TRANSPORT_OF_COPIES = 'transport_of_copies';

    public const CATEGORY_FEATURE          = 'feature';
    public const CATEGORY_BUGFIX           = 'bugfix';
    public const CATEGORY_CONFIGURATION    = 'configuration';
    public const CATEGORY_DATA_MIGRATION   = 'data_migration';

    public const ENV_QUALITY    = 'quality';
    public const ENV_PRODUCTION = 'production';
    public const ENV_STAGING    = 'staging';

    protected $fillable = [
        'uuid',
        'organization_id',
        'request_number',
        'description',
        'request_type',
        'category',
        'target_environment',
        'status',
        'created_by',
        'released_by',
        'released_at',
        'imported_at',
        'import_log',
    ];

    protected $casts = [
        'released_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function objects(): HasMany
    {
        return $this->hasMany(ChangeTransportObject::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ChangeTransportObjectAssignment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ChangeTransportLog::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }
}
