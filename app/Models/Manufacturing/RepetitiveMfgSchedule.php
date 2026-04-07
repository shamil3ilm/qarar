<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepetitiveMfgSchedule extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_PLANNED     = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'organization_id',
        'product_id',
        'production_version_id',
        'production_line_id',
        'schedule_date_from',
        'schedule_date_to',
        'total_planned_quantity',
        'total_confirmed_quantity',
        'status',
        'created_by',
    ];

    protected $casts = [
        'schedule_date_from'       => 'date',
        'schedule_date_to'         => 'date',
        'total_planned_quantity'   => 'decimal:4',
        'total_confirmed_quantity' => 'decimal:4',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productionVersion(): BelongsTo
    {
        return $this->belongsTo(ProductionVersion::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RepetitiveMfgScheduleLine::class, 'repetitive_mfg_schedule_id')
            ->orderBy('schedule_date');
    }

    public function backflushes(): HasMany
    {
        return $this->hasMany(RepetitiveMfgBackflush::class, 'repetitive_mfg_schedule_id')
            ->orderByDesc('backflush_date');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS]);
    }
}
