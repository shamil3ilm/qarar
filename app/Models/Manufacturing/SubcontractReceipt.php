<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractReceipt extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';

    protected $guarded = ['id'];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrder::class, 'order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubcontractReceiptLine::class, 'receipt_id');
    }

    // Helpers

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function getTotalCost(): float
    {
        return (float) $this->lines()->sum('total_cost');
    }
}
