<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransaction extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    // Transaction type constants
    public const TYPE_ACQUISITION = 'acquisition';
    public const TYPE_DEPRECIATION = 'depreciation';
    public const TYPE_IMPAIRMENT = 'impairment';
    public const TYPE_REVALUATION = 'revaluation';
    public const TYPE_PARTIAL_DISPOSAL = 'partial_disposal';
    public const TYPE_FULL_DISPOSAL = 'full_disposal';
    public const TYPE_WRITE_OFF = 'write_off';
    public const TYPE_TRANSFER = 'transfer';

    public const TYPES = [
        self::TYPE_ACQUISITION,
        self::TYPE_DEPRECIATION,
        self::TYPE_IMPAIRMENT,
        self::TYPE_REVALUATION,
        self::TYPE_PARTIAL_DISPOSAL,
        self::TYPE_FULL_DISPOSAL,
        self::TYPE_WRITE_OFF,
        self::TYPE_TRANSFER,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeForPeriod($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}
