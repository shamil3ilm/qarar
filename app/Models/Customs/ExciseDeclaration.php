<?php

declare(strict_types=1);

namespace App\Models\Customs;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExciseDeclaration extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_AMENDED = 'amended';

    // Declaration type constants
    public const TYPE_PERIODIC = 'periodic';
    public const TYPE_AD_HOC = 'ad_hoc';
    public const TYPE_AMENDMENT = 'amendment';

    protected $fillable = [
        'organization_id',
        'declaration_number',
        'declaration_type',
        'period_from',
        'period_to',
        'total_excisable_value',
        'total_excise_duty',
        'total_deductions',
        'net_payable',
        'status',
        'submitted_at',
        'paid_at',
        'payment_reference',
        'notes',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'total_excisable_value' => 'decimal:4',
            'total_excise_duty' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'net_payable' => 'decimal:4',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->declaration_number)) {
                $model->declaration_number = static::generateNumber($model->organization_id);
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
        return $this->hasMany(ExciseDeclarationItem::class, 'declaration_id');
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

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeForPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->where('period_from', '>=', $from)
            ->where('period_to', '<=', $to);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->count() > 0;
    }

    public function canPay(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->total_excisable_value = $items->sum('excisable_value');
        $this->total_excise_duty = $items->sum('excise_amount');
        $this->net_payable = (float) $this->total_excise_duty - (float) $this->total_deductions;

        $this->saveQuietly();
    }

    public static function generateNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $key = "EXD-{$year}-";

        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('declaration_number', 'like', "{$key}%")
            ->orderByDesc('id')
            ->value('declaration_number');

        $sequence = $last ? (int) substr($last, strlen($key)) + 1 : 1;

        return $key . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
