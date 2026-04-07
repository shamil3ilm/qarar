<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaArrangementItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'quota_arrangement_id',
        'vendor_id',
        'purchasing_info_record_id',
        'quota_percentage',
        'min_lot_size',
        'max_lot_size',
        'allocated_quantity',
        'last_assigned_at',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'quota_percentage'    => 'decimal:2',
            'min_lot_size'        => 'decimal:4',
            'max_lot_size'        => 'decimal:4',
            'allocated_quantity'  => 'decimal:4',
            'last_assigned_at'    => 'datetime',
            'is_blocked'          => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function arrangement(): BelongsTo
    {
        return $this->belongsTo(QuotaArrangement::class, 'quota_arrangement_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function infoRecord(): BelongsTo
    {
        return $this->belongsTo(PurchasingInfoRecord::class, 'purchasing_info_record_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_blocked', false);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Quota rating: allocated_quantity / quota_percentage.
     * Lower value means this item should receive the next assignment.
     * Returns 0 when quota_percentage is zero to prevent division by zero.
     */
    public function getQuotaRating(): float
    {
        $percentage = (float) $this->quota_percentage;

        if ($percentage <= 0.0) {
            return 0.0;
        }

        return (float) $this->allocated_quantity / $percentage;
    }
}
