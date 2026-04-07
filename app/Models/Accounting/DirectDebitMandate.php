<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class DirectDebitMandate extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_collection_date' => 'date',
            'next_collection_date'  => 'date',
            'last_collection_date'  => 'date',
            'signed_date'           => 'date',
            'cancellation_date'     => 'date',
            'amount'                => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'counterparty_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(DirectDebitCollection::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('next_collection_date')
            ->whereDate('next_collection_date', '<=', now()->toDateString());
    }

    public function scopeForCounterparty(Builder $query, int $counterpartyId): Builder
    {
        return $query->where('counterparty_id', $counterpartyId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function calculateNextDate(): ?Carbon
    {
        $base = $this->next_collection_date ?? $this->first_collection_date;

        if ($base === null) {
            return null;
        }

        return match ($this->frequency) {
            'weekly'     => $base->addWeek(),
            'biweekly'   => $base->addWeeks(2),
            'monthly'    => $base->addMonth(),
            'quarterly'  => $base->addMonths(3),
            'annually'   => $base->addYear(),
            'one_time'   => null,
            default      => null,
        };
    }

    public function isExpired(): bool
    {
        if ($this->max_collections !== null && $this->total_collections >= $this->max_collections) {
            return true;
        }

        if ($this->frequency === 'one_time' && $this->total_collections > 0) {
            return true;
        }

        return false;
    }
}
