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

class ParkedDocument extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'posting_date'  => 'date',
            'document_data' => 'array',
            'total_debit'   => 'decimal:4',
            'total_credit'  => 'decimal:4',
        ];
    }

    public const DOC_TYPE_JOURNAL_ENTRY = 'journal_entry';
    public const DOC_TYPE_INVOICE       = 'invoice';
    public const DOC_TYPE_PAYMENT       = 'payment';

    public const STATUS_PARKED           = 'parked';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_POSTED           = 'posted';
    public const STATUS_REJECTED         = 'rejected';

    public function parkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parked_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPostable(): bool
    {
        return in_array($this->status, [self::STATUS_PARKED, self::STATUS_PENDING_APPROVAL], true);
    }

    public function scopeParked($query)
    {
        return $query->whereIn('status', [self::STATUS_PARKED, self::STATUS_PENDING_APPROVAL]);
    }
}
