<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDeliveryRecord extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'purchase_order_id',
        'supplier_id',
        'promised_date',
        'actual_date',
        'quantity_ordered',
        'quantity_received',
        'is_on_time',
        'is_complete',
        'quality_accepted',
        'defect_quantity',
        'notes',
    ];

    protected $casts = [
        'promised_date'    => 'date',
        'actual_date'      => 'date',
        'quantity_ordered'  => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'defect_quantity'   => 'decimal:4',
        'is_on_time'        => 'boolean',
        'is_complete'       => 'boolean',
        'quality_accepted'  => 'boolean',
    ];

    // Relationships

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    // Helpers

    public function getFillRate(): float
    {
        $ordered = (float) $this->quantity_ordered;

        if ($ordered <= 0.0) {
            return 0.0;
        }

        return round(((float) $this->quantity_received / $ordered) * 100, 2);
    }

    public function getDefectRate(): float
    {
        $received = (float) $this->quantity_received;

        if ($received <= 0.0) {
            return 0.0;
        }

        return round(((float) $this->defect_quantity / $received) * 100, 2);
    }

    /**
     * Days between promised date and actual delivery date.
     * Positive = late, negative = early, zero = on time.
     */
    public function getLeadTimeDays(): ?int
    {
        if ($this->actual_date === null) {
            return null;
        }

        return (int) $this->promised_date->diffInDays($this->actual_date, false);
    }
}
