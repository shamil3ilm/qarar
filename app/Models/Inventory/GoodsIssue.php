<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsIssue extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    // ── Status constants ──────────────────────────────────────────────────────

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    // ── Movement type constants ───────────────────────────────────────────────

    public const MOVEMENT_SALES_DELIVERY   = 'sales_delivery';
    public const MOVEMENT_PRODUCTION_ISSUE = 'production_issue';
    public const MOVEMENT_SCRAPPING        = 'scrapping';
    public const MOVEMENT_TRANSFER         = 'transfer';
    public const MOVEMENT_OTHER            = 'other';

    // ── Mass-assignable columns ───────────────────────────────────────────────

    protected $guarded = ['id'];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'gi_date'        => 'date',
            'posted_at'      => 'datetime',
            'reversed_at'    => 'datetime',
            'total_quantity' => 'decimal:4',
            'total_value'    => 'decimal:4',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsIssueLine::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Polymorphic relation to the source document (Invoice, SalesOrder, WorkOrder, …).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Query scopes ──────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeByMovementType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    // ── State helpers ─────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function canBePosted(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->lines()->count() > 0;
    }

    public function canBeReversed(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /**
     * Returns a human-readable label for the movement type.
     */
    public function getMovementTypeLabel(): string
    {
        return match ($this->movement_type) {
            self::MOVEMENT_SALES_DELIVERY   => 'Sales Delivery',
            self::MOVEMENT_PRODUCTION_ISSUE => 'Production Issue',
            self::MOVEMENT_SCRAPPING        => 'Scrapping',
            self::MOVEMENT_TRANSFER         => 'Transfer',
            self::MOVEMENT_OTHER            => 'Other',
            default                         => $this->movement_type,
        };
    }

    /**
     * Recalculate header totals from line values.
     */
    public function recalculateTotals(): void
    {
        $this->update([
            'total_quantity' => $this->lines()->sum('quantity'),
            'total_value'    => $this->lines()->sum('total_value'),
        ]);
    }
}
