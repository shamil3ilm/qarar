<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_obligation_id',
        'event_date',
        'amount_recognized',
        'journal_entry_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date'        => 'date',
            'amount_recognized' => 'decimal:4',
        ];
    }

    public function performanceObligation(): BelongsTo
    {
        return $this->belongsTo(PerformanceObligation::class, 'performance_obligation_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
