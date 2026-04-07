<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CurrencyRevaluation extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'revaluation_number',
        'revaluation_date',
        'currency_code',
        'old_rate',
        'new_rate',
        'base_currency',
        'total_unrealized_gain',
        'total_unrealized_loss',
        'net_gain_loss',
        'gain_loss_account_id',
        'journal_entry_id',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'revaluation_date' => 'date',
            'old_rate' => 'decimal:8',
            'new_rate' => 'decimal:8',
            'total_unrealized_gain' => 'decimal:4',
            'total_unrealized_loss' => 'decimal:4',
            'net_gain_loss' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->revaluation_number)) {
                $model->revaluation_number = static::generateNumber($model->organization_id);
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(CurrencyRevaluationItem::class, 'revaluation_id');
    }

    public function gainLossAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gain_loss_account_id');
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

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', $currencyCode);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('revaluation_date', [$from, $to]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->count() > 0;
    }

    public function canReverse(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $gains = $items->where('gain_loss_amount', '>', 0)->sum('gain_loss_amount');
        $losses = $items->where('gain_loss_amount', '<', 0)->sum('gain_loss_amount');

        $this->total_unrealized_gain = abs($gains);
        $this->total_unrealized_loss = abs($losses);
        $this->net_gain_loss = $gains + $losses;

        $this->saveQuietly();
    }

    public static function generateNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $key = "REVAL-{$year}-";

        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('revaluation_number', 'like', "{$key}%")
            ->orderByDesc('id')
            ->value('revaluation_number');

        $sequence = $last ? (int) substr($last, strlen($key)) + 1 : 1;

        return $key . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
