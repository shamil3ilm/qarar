<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorContract extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_EXPIRED    = 'expired';
    public const STATUS_TERMINATED = 'terminated';

    public const TYPE_SUPPLY        = 'supply';
    public const TYPE_SERVICE       = 'service';
    public const TYPE_FRAMEWORK     = 'framework';
    public const TYPE_BLANKET_ORDER = 'blanket_order';

    protected $table = 'vendor_contracts';

    protected $guarded = ['id'];

    protected $casts = [
        'start_date'      => 'date',
        'end_date'        => 'date',
        'signed_at'       => 'date',
        'terminated_at'   => 'date',
        'auto_renew'      => 'boolean',
        'total_value'     => 'decimal:2',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorContractItem::class, 'vendor_contract_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ----------------------------------------------------------------
    // Business Logic
    // ----------------------------------------------------------------

    public function isExpired(): bool
    {
        if ($this->end_date === null) {
            return false;
        }

        return $this->end_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->end_date === null) {
            return false;
        }

        $threshold = Carbon::today()->addDays($days);

        return $this->end_date->lte($threshold) && $this->end_date->isFuture();
    }
}
