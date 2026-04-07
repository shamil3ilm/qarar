<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RfqHeader extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_AWARDED = 'awarded';

    protected $table = 'rfq_headers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'submission_deadline' => 'date',
            'delivery_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class, 'rfq_id')->orderBy('sort_order');
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(RfqVendor::class, 'rfq_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RfqQuote::class, 'rfq_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSent(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }
}
