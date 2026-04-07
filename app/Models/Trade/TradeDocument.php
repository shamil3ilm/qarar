<?php

declare(strict_types=1);

namespace App\Models\Trade;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TradeDocument extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    // Document type constants
    public const TYPE_BILL_OF_LADING = 'bill_of_lading';
    public const TYPE_AIRWAY_BILL = 'airway_bill';
    public const TYPE_CERTIFICATE_OF_ORIGIN = 'certificate_of_origin';
    public const TYPE_PACKING_LIST = 'packing_list';
    public const TYPE_COMMERCIAL_INVOICE = 'commercial_invoice';
    public const TYPE_INSURANCE_CERT = 'insurance_cert';
    public const TYPE_INSPECTION_CERT = 'inspection_cert';
    public const TYPE_PHYTOSANITARY = 'phytosanitary';
    public const TYPE_FUMIGATION = 'fumigation';
    public const TYPE_CUSTOMS_INVOICE = 'customs_invoice';
    public const TYPE_CONSULAR_INVOICE = 'consular_invoice';

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'organization_id',
        'document_type',
        'document_number',
        'reference',
        'source_type',
        'source_id',
        'contact_id',
        'issued_date',
        'expiry_date',
        'issuing_authority',
        'issuing_country',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expiry_date' => 'date',
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    public function scopeForEntity(Builder $query, string $sourceType, int $sourceId): Builder
    {
        return $query->where('source_type', $sourceType)
            ->where('source_id', $sourceId);
    }

    public function scopeExpiring(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now())
            ->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function hasFile(): bool
    {
        return !empty($this->file_path);
    }
}
