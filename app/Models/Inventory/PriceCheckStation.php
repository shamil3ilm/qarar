<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Core\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceCheckStation extends Model
{
    use BelongsToOrganization, HasUuid, HasFactory;

    public const DEVICE_KIOSK = 'kiosk';
    public const DEVICE_HANDHELD = 'handheld';
    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_TABLET = 'tablet';
    public const DEVICE_POS = 'pos';

    public const SCANNER_LASER = 'laser';
    public const SCANNER_CAMERA = 'camera';
    public const SCANNER_RFID = 'rfid';
    public const SCANNER_NFC = 'nfc';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'name',
        'station_code',
        'location_description',
        'device_type',
        'device_id',
        'scanner_type',
        'scan_barcode',
        'scan_qr',
        'scan_rfid',
        'scan_nfc',
        'manual_entry',
        'show_price',
        'show_stock',
        'show_promotions',
        'show_alternatives',
        'show_loyalty_points',
        'show_product_image',
        'show_description',
        'show_location',
        'price_list_id',
        'use_customer_price',
        'api_token',
        'status',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_barcode' => 'boolean',
            'scan_qr' => 'boolean',
            'scan_rfid' => 'boolean',
            'scan_nfc' => 'boolean',
            'manual_entry' => 'boolean',
            'show_price' => 'boolean',
            'show_stock' => 'boolean',
            'show_promotions' => 'boolean',
            'show_alternatives' => 'boolean',
            'show_loyalty_points' => 'boolean',
            'show_product_image' => 'boolean',
            'show_description' => 'boolean',
            'show_location' => 'boolean',
            'use_customer_price' => 'boolean',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    protected $hidden = ['api_token'];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PriceCheckLog::class, 'station_id');
    }

    // Business logic
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOnline(): bool
    {
        if (!$this->last_heartbeat_at) {
            return false;
        }

        return $this->last_heartbeat_at->diffInMinutes(now()) < 5;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('last_heartbeat_at', '>=', now()->subMinutes(5));
    }
}
