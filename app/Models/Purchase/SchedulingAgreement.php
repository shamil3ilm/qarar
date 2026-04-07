<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchedulingAgreement extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'product_id',
        'agreement_number',
        'status',
        'valid_from',
        'valid_to',
        'target_quantity',
        'released_quantity',
        'unit_price',
        'currency_code',
        'unit_of_measure',
        'delivery_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'        => 'date',
            'valid_to'          => 'date',
            'target_quantity'   => 'decimal:4',
            'released_quantity' => 'decimal:4',
            'unit_price'        => 'decimal:4',
        ];
    }

    // Relationships

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SaDeliverySchedule::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    // Helpers

    public function getOpenSchedules()
    {
        return $this->schedules()->where('status', SaDeliverySchedule::STATUS_OPEN)->get();
    }

    public function getNextDelivery(): ?SaDeliverySchedule
    {
        return $this->schedules()
            ->where('status', SaDeliverySchedule::STATUS_OPEN)
            ->where('schedule_date', '>=', today())
            ->orderBy('schedule_date')
            ->first();
    }
}
