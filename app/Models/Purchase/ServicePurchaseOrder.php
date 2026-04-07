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
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePurchaseOrder extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_ACCEPTED = 'partially_accepted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'po_number',
        'vendor_id',
        'description',
        'total_value',
        'currency',
        'status',
        'valid_from',
        'valid_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'total_value' => 'decimal:4',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ServicePoLine::class)->orderBy('line_number');
    }

    public function entrySheets(): HasMany
    {
        return $this->hasMany(ServiceEntrySheet::class);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
