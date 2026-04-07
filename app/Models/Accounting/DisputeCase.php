<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisputeCase extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'disputed_amount' => 'decimal:4',
            'resolved_amount' => 'decimal:4',
            'due_date'        => 'date',
        ];
    }

    public const STATUS_OPEN      = 'open';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_RESOLVED  = 'resolved';
    public const STATUS_CLOSED    = 'closed';

    public const REASON_PRICING   = 'pricing';
    public const REASON_QUALITY   = 'quality';
    public const REASON_QUANTITY  = 'quantity';
    public const REASON_DELIVERY  = 'delivery';
    public const REASON_DUPLICATE = 'duplicate';
    public const REASON_OTHER     = 'other';

    public const DOC_TYPE_INVOICE          = 'invoice';
    public const DOC_TYPE_PAYMENT_RECEIVED = 'payment_received';
    public const DOC_TYPE_CREDIT_NOTE      = 'credit_note';

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_REVIEW, self::STATUS_ESCALATED]);
    }

    public function isCloseable(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
