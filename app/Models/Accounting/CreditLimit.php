<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditLimit extends Model
{
    use HasFactory, HasAuditTrail, HasUuid, SoftDeletes;

    public const RISK_LOW     = 'low';
    public const RISK_MEDIUM  = 'medium';
    public const RISK_HIGH    = 'high';
    public const RISK_BLOCKED = 'blocked';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'credit_limit'       => 'decimal:4',
            'valid_from'         => 'date',
            'valid_until'        => 'date',
            'payment_terms_days' => 'integer',
            'last_reviewed_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isBlocked(): bool
    {
        return $this->risk_class === self::RISK_BLOCKED;
    }

    public function isValid(): bool
    {
        $now = now()->toDateString();

        return $this->valid_from <= $now
            && ($this->valid_until === null || $this->valid_until >= $now);
    }

    public function scopeActive($query)
    {
        $now = now()->toDateString();

        return $query->where('valid_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });
    }
}
