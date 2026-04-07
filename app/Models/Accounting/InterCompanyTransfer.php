<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterCompanyTransfer extends Model
{
    use HasFactory;
    use HasStateMachine;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function getStateColumn(): string
    {
        return 'status';
    }

    protected function getStateTransitions(): array
    {
        return [
            self::STATUS_PENDING   => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED  => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];
    }
}