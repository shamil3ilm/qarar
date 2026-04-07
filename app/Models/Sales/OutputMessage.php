<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutputMessage extends Model
{
    use HasFactory;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT       = 'sent';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'output_type_id',
        'document_type',
        'document_id',
        'status',
        'medium',
        'recipient',
        'scheduled_at',
        'sent_at',
        'error_message',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at'      => 'datetime',
            'retry_count'  => 'integer',
        ];
    }

    public function outputType(): BelongsTo
    {
        return $this->belongsTo(OutputType::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeScheduledBefore($query, \DateTimeInterface $dateTime)
    {
        return $query->where(function ($q) use ($dateTime) {
            $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $dateTime);
        });
    }

    public function scopeForDocument($query, string $type, int $id)
    {
        return $query->where('document_type', $type)
            ->where('document_id', $id);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
