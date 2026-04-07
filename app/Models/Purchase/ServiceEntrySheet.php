<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceEntrySheet extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'organization_id',
        'ses_number',
        'service_purchase_order_id',
        'vendor_id',
        'service_period_from',
        'service_period_to',
        'description',
        'status',
        'submitted_by',
        'approved_by',
        'approved_at',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'service_period_from' => 'date',
            'service_period_to' => 'date',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function servicePurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ServicePurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ServiceEntrySheetLine::class);
    }

    public function acceptance(): HasOne
    {
        return $this->hasOne(ServiceAcceptance::class);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
