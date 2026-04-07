<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class RmaRequest extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'rma_requests';

    public const TYPE_SALES = 'sales_return';
    public const TYPE_PURCHASE = 'purchase_return';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'organization_id',
        'rma_number',
        'rma_type',
        'customer_id',
        'supplier_id',
        'invoice_id',
        'bill_id',
        'description',
        'requested_resolution',
        'status',
        'request_date',
        'expiry_date',
        'sales_return_id',
        'purchase_return_id',
        'approved_by',
        'approved_at',
        'approval_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'expiry_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RmaItem::class, 'rma_request_id');
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function approve(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }
}
