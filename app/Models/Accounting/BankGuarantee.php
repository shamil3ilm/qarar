<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankGuarantee extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date'      => 'date',
            'expiry_date'     => 'date',
            'claim_deadline'  => 'date',
            'claim_date'      => 'date',
            'amount'          => 'decimal:4',
            'bank_charges'    => 'decimal:4',
            'claim_amount'    => 'decimal:4',
            'is_auto_renewed' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'bank_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'beneficiary_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'applicant_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'related_purchase_order_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'related_sales_order_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->whereDate('expiry_date', '>=', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired')
            ->orWhere(function (Builder $q): void {
                $q->where('status', 'active')
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<', now());
            });
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('direction', 'issued');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('direction', 'received');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    public function daysToExpiry(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        $days = (int) now()->diffInDays($this->expiry_date, false);

        return $days >= 0 ? $days : null;
    }

    public function canClaim(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->claim_deadline !== null && $this->claim_deadline->isPast()) {
            return false;
        }

        return true;
    }
}
