<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CpqConfiguration extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'cpq_configurations';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_VALID     = 'valid';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'organization_id',
        'cpq_configurable_product_id',
        'contact_id',
        'quotation_id',
        'configuration_code',
        'status',
        'total_price',
        'currency_code',
        'valid_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:4',
            'valid_until' => 'date',
        ];
    }

    public function configurableProduct(): BelongsTo
    {
        return $this->belongsTo(CpqConfigurableProduct::class, 'cpq_configurable_product_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CpqConfigurationItem::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    public function canConvert(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_VALID], true)
            && ! $this->isExpired();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_VALID]);
    }
}
