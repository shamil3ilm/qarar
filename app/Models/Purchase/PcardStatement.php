<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PcardStatement extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_RECONCILED = 'reconciled';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'organization_id',
        'pcard_id',
        'statement_period_start',
        'statement_period_end',
        'total_amount',
        'currency',
        'status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_period_start' => 'date',
            'statement_period_end' => 'date',
            'total_amount' => 'decimal:4',
        ];
    }

    public function pcard(): BelongsTo
    {
        return $this->belongsTo(Pcard::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PcardTransaction::class);
    }

    public function isFullyReconciled(): bool
    {
        return $this->transactions()
            ->where('status', '!=', PcardTransaction::STATUS_RECONCILED)
            ->doesntExist();
    }
}
