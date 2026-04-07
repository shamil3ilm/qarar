<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCreditLimit extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'credit_limit'       => 'decimal:2',
            'current_exposure'   => 'decimal:2',
            'available_credit'   => 'decimal:2',
            'payment_terms_days' => 'integer',
            'is_active'          => 'boolean',
            'approved_at'        => 'datetime',
            'last_reviewed_at'   => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CreditLimitHistory::class, 'contact_id', 'contact_id')
            ->where('organization_id', $this->organization_id);
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    /**
     * Returns true when the current exposure has met or exceeded the credit limit.
     */
    public function isExceeded(): bool
    {
        return bccomp(
            (string) $this->current_exposure,
            (string) $this->credit_limit,
            4
        ) >= 0;
    }

    /**
     * Returns utilization as a percentage (0–100+).
     * Returns 0.0 when the credit limit is zero to avoid division by zero.
     */
    public function getUtilizationPct(): float
    {
        $limit = (float) $this->credit_limit;

        if ($limit <= 0.0) {
            return 0.0;
        }

        return (float) bcdiv(
            bcmul((string) $this->current_exposure, '100', 6),
            (string) $limit,
            2
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRisk($query, string $riskCategory)
    {
        return $query->where('risk_category', $riskCategory);
    }
}
