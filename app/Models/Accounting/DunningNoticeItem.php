<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DunningNoticeItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'invoice_date'       => 'date',
            'due_date'           => 'date',
            'original_amount'    => 'decimal:4',
            'outstanding_amount' => 'decimal:4',
            'days_overdue'       => 'integer',
        ];
    }

    public function dunningNotice(): BelongsTo
    {
        return $this->belongsTo(DunningNotice::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
