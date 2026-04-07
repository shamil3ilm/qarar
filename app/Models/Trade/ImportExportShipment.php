<?php

declare(strict_types=1);

namespace App\Models\Trade;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\Customs\CustomsDeclaration;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportExportShipment extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    // Shipment type constants
    public const TYPE_IMPORT = 'import';
    public const TYPE_EXPORT = 'export';

    // Transport mode constants
    public const TRANSPORT_SEA = 'sea';
    public const TRANSPORT_AIR = 'air';
    public const TRANSPORT_ROAD = 'road';
    public const TRANSPORT_RAIL = 'rail';
    public const TRANSPORT_MULTIMODAL = 'multimodal';
    public const TRANSPORT_COURIER = 'courier';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_AT_PORT = 'at_port';
    public const STATUS_CUSTOMS_CLEARANCE = 'customs_clearance';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'shipment_number',
        'shipment_type',
        'purchase_order_id',
        'invoice_id',
        'contact_id',
        'incoterm',
        'transport_mode',
        'vessel_name',
        'voyage_number',
        'container_numbers',
        'bill_of_lading',
        'airway_bill',
        'port_of_loading',
        'port_of_discharge',
        'place_of_delivery',
        'country_of_origin',
        'country_of_destination',
        'estimated_departure',
        'actual_departure',
        'estimated_arrival',
        'actual_arrival',
        'delivery_date',
        'currency_code',
        'exchange_rate',
        'fob_value',
        'freight_value',
        'insurance_value',
        'cif_value',
        'other_charges',
        'gross_weight_kg',
        'net_weight_kg',
        'total_packages',
        'total_cbm',
        'customs_declaration_id',
        'lc_id',
        'landed_cost_voucher_id',
        'insurance_policy_number',
        'insurance_company',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'container_numbers' => 'array',
            'estimated_departure' => 'date',
            'actual_departure' => 'date',
            'estimated_arrival' => 'date',
            'actual_arrival' => 'date',
            'delivery_date' => 'date',
            'exchange_rate' => 'decimal:8',
            'fob_value' => 'decimal:4',
            'freight_value' => 'decimal:4',
            'insurance_value' => 'decimal:4',
            'cif_value' => 'decimal:4',
            'other_charges' => 'decimal:4',
            'gross_weight_kg' => 'decimal:4',
            'net_weight_kg' => 'decimal:4',
            'total_packages' => 'integer',
            'total_cbm' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->shipment_number)) {
                $model->shipment_number = static::generateNumber($model->organization_id, $model->shipment_type);
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportExportShipmentItem::class, 'shipment_id');
    }

    public function customsDeclaration(): BelongsTo
    {
        return $this->belongsTo(CustomsDeclaration::class);
    }

    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class, 'lc_id');
    }

    public function landedCostVoucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class);
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
        return $query->where('shipment_type', $type);
    }

    public function scopeImports(Builder $query): Builder
    {
        return $query->where('shipment_type', self::TYPE_IMPORT);
    }

    public function scopeExports(Builder $query): Builder
    {
        return $query->where('shipment_type', self::TYPE_EXPORT);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_IN_TRANSIT, self::STATUS_AT_PORT]);
    }

    public function scopePendingClearance(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CUSTOMS_CLEARANCE);
    }

    public function scopeForContact(Builder $query, int $contactId): Builder
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeArrivingSoon(Builder $query, int $days = 7): Builder
    {
        return $query->where('estimated_arrival', '<=', now()->addDays($days))
            ->where('estimated_arrival', '>=', now())
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_TRANSIT]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_TRANSIT]);
    }

    public function recalculateCifValue(): void
    {
        $this->cif_value = (float) $this->fob_value + (float) $this->freight_value + (float) $this->insurance_value;
        $this->saveQuietly();
    }

    public static function generateNumber(int $organizationId, string $type): string
    {
        $prefix = $type === self::TYPE_IMPORT ? 'IMP' : 'EXP';
        $year = now()->format('Y');
        $key = "SHP-{$prefix}-{$year}-";

        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('shipment_number', 'like', "{$key}%")
            ->orderByDesc('id')
            ->value('shipment_number');

        $sequence = $last ? (int) substr($last, strlen($key)) + 1 : 1;

        return $key . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
