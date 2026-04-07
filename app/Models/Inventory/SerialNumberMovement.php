<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialNumberMovement extends Model
{
    use BelongsToOrganization, HasUuid;

    public const TYPE_RECEIPT  = 'receipt';
    public const TYPE_ISSUE    = 'issue';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_RETURN   = 'return';
    public const TYPE_SCRAP    = 'scrap';

    protected $fillable = [
        'organization_id',
        'serial_number_id',
        'movement_type',
        'from_warehouse_id',
        'to_warehouse_id',
        'document_type',
        'document_id',
        'moved_by',
        'moved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
