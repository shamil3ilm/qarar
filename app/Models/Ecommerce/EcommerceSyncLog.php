<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceSyncLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Sync type values
    public const TYPE_PRODUCTS = 'products';
    public const TYPE_ORDERS = 'orders';
    public const TYPE_INVENTORY = 'inventory';
    public const TYPE_CUSTOMERS = 'customers';

    // Direction values
    public const DIRECTION_PUSH = 'push';
    public const DIRECTION_PULL = 'pull';

    // Status values
    public const STATUS_STARTED = 'started';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'total_records' => 'integer',
            'processed_records' => 'integer',
            'failed_records' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(EcommerceChannel::class, 'channel_id');
    }
}