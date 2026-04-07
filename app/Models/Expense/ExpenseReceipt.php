<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExpenseReceipt extends Model
{
    use HasFactory;
    protected $fillable = [
        'expense_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'ocr_text',
        'ocr_data',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'ocr_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExpenseReceipt $receipt) {
            if (!$receipt->uploaded_by) {
                $receipt->uploaded_by = auth()->id();
            }
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
