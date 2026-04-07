<?php

declare(strict_types=1);

namespace App\Models\Customs;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomsDeclaration extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $guarded = ['id'];

    // Status values
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ASSESSED = 'assessed';
    public const STATUS_DUTY_PAID = 'duty_paid';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_ASSESSED,
        self::STATUS_DUTY_PAID,
        self::STATUS_CLEARED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    // Declaration types
    public const TYPE_IMPORT = 'import';
    public const TYPE_EXPORT = 'export';
    public const TYPE_TRANSIT = 'transit';
    public const TYPE_RE_EXPORT = 're_export';
    public const TYPE_TEMPORARY_IMPORT = 'temporary_import';
    public const TYPE_TEMPORARY_EXPORT = 'temporary_export';

    public const DECLARATION_TYPES = [
        self::TYPE_IMPORT,
        self::TYPE_EXPORT,
        self::TYPE_TRANSIT,
        self::TYPE_RE_EXPORT,
        self::TYPE_TEMPORARY_IMPORT,
        self::TYPE_TEMPORARY_EXPORT,
    ];

    // Transport modes
    public const TRANSPORT_SEA = 'sea';
    public const TRANSPORT_AIR = 'air';
    public const TRANSPORT_LAND = 'land';
    public const TRANSPORT_RAIL = 'rail';
    public const TRANSPORT_MULTIMODAL = 'multimodal';

    public const TRANSPORT_MODES = [
        self::TRANSPORT_SEA,
        self::TRANSPORT_AIR,
        self::TRANSPORT_LAND,
        self::TRANSPORT_RAIL,
        self::TRANSPORT_MULTIMODAL,
    ];

    // Customs regimes
    public const REGIME_FREE_CIRCULATION = 'free_circulation';
    public const REGIME_TRANSIT = 'transit';
    public const REGIME_CUSTOMS_WAREHOUSE = 'customs_warehouse';
    public const REGIME_TEMPORARY_ADMISSION = 'temporary_admission';
    public const REGIME_INWARD_PROCESSING = 'inward_processing';
    public const REGIME_FREE_ZONE = 'free_zone';

    public const CUSTOMS_REGIMES = [
        self::REGIME_FREE_CIRCULATION,
        self::REGIME_TRANSIT,
        self::REGIME_CUSTOMS_WAREHOUSE,
        self::REGIME_TEMPORARY_ADMISSION,
        self::REGIME_INWARD_PROCESSING,
        self::REGIME_FREE_ZONE,
    ];

    protected function casts(): array
    {
        return [
            'declaration_date' => 'date',
            'submitted_at' => 'datetime',
            'assessed_at' => 'datetime',
            'duty_paid_at' => 'datetime',
            'cleared_at' => 'datetime',
            'exchange_rate' => 'decimal:8',
            'fob_value' => 'decimal:4',
            'freight_value' => 'decimal:4',
            'insurance_value' => 'decimal:4',
            'cif_value' => 'decimal:4',
            'assessable_value' => 'decimal:4',
            'total_duty' => 'decimal:4',
            'total_vat' => 'decimal:4',
            'total_excise' => 'decimal:4',
            'total_fees' => 'decimal:4',
            'total_payable' => 'decimal:4',
            'gross_weight_kg' => 'decimal:4',
            'net_weight_kg' => 'decimal:4',
            'total_packages' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Booted
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->declaration_number)) {
                $model->declaration_number = static::generateNumber(
                    $model->organization_id ?? auth()->user()?->organization_id,
                    $model->declaration_type
                );
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_DRAFT;
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
        return $this->hasMany(CustomsDeclarationItem::class, 'declaration_id');
    }

    public function importerExporter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sales\Contact::class, 'importer_exporter_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sales\Contact::class, 'broker_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('declaration_type', $type);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('declaration_date', [$from, $to]);
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

    public function canAssess(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canPayDuty(): bool
    {
        return $this->status === self::STATUS_ASSESSED;
    }

    public function canClear(): bool
    {
        return $this->status === self::STATUS_DUTY_PAID;
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->total_duty = $items->sum('duty_amount');
        $this->total_vat = $items->sum('vat_amount');
        $this->total_excise = $items->sum('excise_amount');
        $this->total_payable = (float) $this->total_duty
            + (float) $this->total_vat
            + (float) $this->total_excise
            + (float) $this->total_fees;

        $this->saveQuietly();
    }

    public static function generateNumber(?int $organizationId, string $declarationType): string
    {
        $typeCode = match ($declarationType) {
            self::TYPE_IMPORT => 'IMP',
            self::TYPE_EXPORT => 'EXP',
            self::TYPE_TRANSIT => 'TRN',
            self::TYPE_RE_EXPORT => 'REX',
            self::TYPE_TEMPORARY_IMPORT => 'TMP',
            self::TYPE_TEMPORARY_EXPORT => 'TEX',
            default => 'DCL',
        };

        $year = now()->format('Y');
        $key = "CD-{$typeCode}-{$year}-";

        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('declaration_number', 'like', "{$key}%")
            ->orderByDesc('id')
            ->value('declaration_number');

        $sequence = $last ? (int) substr($last, strlen($key)) + 1 : 1;

        return $key . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
