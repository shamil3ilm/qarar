<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    // Payment methods
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CASH = 'cash';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_ONLINE = 'online';

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}