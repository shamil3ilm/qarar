<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Contact;
use App\Models\Sales\PaymentReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckRegisterEntry extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'check_date'  => 'date',
            'amount'      => 'decimal:4',
            'printed_at'  => 'datetime',
            'issued_at'   => 'datetime',
            'cleared_at'  => 'datetime',
            'bounced_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function checkBook(): BelongsTo
    {
        return $this->belongsTo(CheckBook::class);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'payee_id');
    }

    public function paymentMade(): BelongsTo
    {
        return $this->belongsTo(PaymentMade::class);
    }

    public function paymentReceived(): BelongsTo
    {
        return $this->belongsTo(PaymentReceived::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'presented']);
    }

    public function scopeCleared(Builder $query): Builder
    {
        return $query->where('status', 'cleared');
    }

    public function scopeBounced(Builder $query): Builder
    {
        return $query->where('status', 'bounced');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isStale(): bool
    {
        if ($this->status !== 'issued' || $this->issued_at === null) {
            return false;
        }

        return $this->issued_at->diffInMonths(now()) >= 6;
    }
}
