<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorAdvance extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_PAID               = 'paid';
    public const STATUS_PARTIALLY_ADJUSTED = 'partially_adjusted';
    public const STATUS_FULLY_ADJUSTED     = 'fully_adjusted';
    public const STATUS_REFUNDED           = 'refunded';

    protected $table = 'vendor_advances';

    protected $guarded = ['id'];

    protected $casts = [
        'amount'           => 'decimal:2',
        'adjusted_amount'  => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'payment_date'     => 'date',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(VendorAdvanceAdjustment::class, 'vendor_advance_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
