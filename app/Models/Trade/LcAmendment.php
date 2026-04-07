<?php

declare(strict_types=1);

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LcAmendment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Status values
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'amendment_date' => 'date',
        ];
    }

    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class, 'letter_of_credit_id');
    }
}