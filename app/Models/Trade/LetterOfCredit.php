<?php

declare(strict_types=1);

namespace App\Models\Trade;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LetterOfCredit extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $table = 'letters_of_credit';

    // LC type constants
    public const TYPE_IMPORT = 'import';
    public const TYPE_EXPORT = 'export';
    public const TYPE_STANDBY = 'standby';
    public const TYPE_REVOLVING = 'revolving';
    public const TYPE_TRANSFERABLE = 'transferable';
    public const TYPE_BACK_TO_BACK = 'back_to_back';

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_AMENDED = 'amended';
    public const STATUS_PARTIALLY_UTILIZED = 'partially_utilized';
    public const STATUS_FULLY_UTILIZED = 'fully_utilized';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'lc_number',
        'lc_type',
        'is_irrevocable',
        'is_confirmed',
        'bank_account_id',
        'issuing_bank',
        'issuing_bank_swift',
        'advising_bank',
        'advising_bank_swift',
        'confirming_bank',
        'negotiating_bank',
        'applicant_id',
        'beneficiary_id',
        'currency_code',
        'amount',
        'tolerance_percent',
        'utilized_amount',
        'available_amount',
        'issue_date',
        'expiry_date',
        'latest_shipment_date',
        'place_of_expiry',
        'presentation_days',
        'incoterm',
        'port_of_loading',
        'port_of_discharge',
        'partial_shipments_allowed',
        'transhipment_allowed',
        'required_documents',
        'terms_and_conditions',
        'special_conditions',
        'status',
        'notes',
        'purchase_order_id',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_irrevocable' => 'boolean',
            'is_confirmed' => 'boolean',
            'amount' => 'decimal:4',
            'tolerance_percent' => 'decimal:2',
            'utilized_amount' => 'decimal:4',
            'available_amount' => 'decimal:4',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'latest_shipment_date' => 'date',
            'presentation_days' => 'integer',
            'partial_shipments_allowed' => 'boolean',
            'transhipment_allowed' => 'boolean',
            'required_documents' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->lc_number)) {
                $model->lc_number = static::generateNumber($model->organization_id);
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
            if (empty($model->available_amount)) {
                $model->available_amount = $model->amount;
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_DRAFT;
            }
        });
    }

    public static function generateNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $key = "LC-{$year}-";

        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('lc_number', 'like', "{$key}%")
            ->orderByDesc('id')
            ->value('lc_number');

        $sequence = $last ? (int) substr($last, strlen($key)) + 1 : 1;

        return $key . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'applicant_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'beneficiary_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(LcAmendment::class, 'lc_id')->orderBy('amendment_number');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(ImportExportShipment::class, 'lc_id');
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

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('lc_type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ISSUED,
            self::STATUS_AMENDED,
            self::STATUS_PARTIALLY_UTILIZED,
        ]);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now())
            ->whereNotIn('status', [self::STATUS_EXPIRED, self::STATUS_CANCELLED, self::STATUS_FULLY_UTILIZED]);
    }

    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', $currencyCode);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPLIED]);
    }

    public function canIssue(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPLIED]);
    }

    public function canAmend(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_AMENDED, self::STATUS_PARTIALLY_UTILIZED]);
    }

    public function canUtilize(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_AMENDED, self::STATUS_PARTIALLY_UTILIZED])
            && (float) $this->available_amount > 0;
    }

    public function canClose(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_AMENDED, self::STATUS_PARTIALLY_UTILIZED]);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function getMaxAmountWithTolerance(): float
    {
        return round((float) $this->amount * (1 + (float) $this->tolerance_percent / 100), 2);
    }

    public function getAvailableBalance(): float
    {
        return (float) $this->available_amount;
    }

    public function utilize(float $amount): void
    {
        $this->utilized_amount = (float) $this->utilized_amount + $amount;
        $this->available_amount = (float) $this->amount - (float) $this->utilized_amount;

        if ((float) $this->available_amount <= 0) {
            $this->status = self::STATUS_FULLY_UTILIZED;
        } else {
            $this->status = self::STATUS_PARTIALLY_UTILIZED;
        }

        $this->save();
    }
}
