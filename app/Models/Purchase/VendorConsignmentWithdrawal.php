<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorConsignmentWithdrawal extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    public const TYPE_PRODUCTION = 'production';
    public const TYPE_SALES      = 'sales';
    public const TYPE_TRANSFER   = 'transfer';
    public const TYPE_SCRAPPING  = 'scrapping';

    protected $table = 'vendor_consignment_withdrawals';

    protected $fillable = [
        'organization_id',
        'vendor_consignment_stock_id',
        'withdrawal_date',
        'quantity_withdrawn',
        'withdrawal_type',
        'reference_type',
        'reference_id',
        'unit_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'withdrawal_date'    => 'date',
            'quantity_withdrawn' => 'decimal:4',
        ];
    }

    public function consignmentStock(): BelongsTo
    {
        return $this->belongsTo(VendorConsignmentStock::class, 'vendor_consignment_stock_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Withdrawals that have not yet been included in a settlement.
     * A withdrawal is unsettled when its stock's vendor has no paid/submitted
     * settlement covering the withdrawal date.
     */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'consignmentStock',
            function (Builder $q): void {
                $q->whereHas('settlements', function (Builder $s): void {
                    $s->whereIn('status', ['submitted', 'paid']);
                });
            }
        );
    }
}
