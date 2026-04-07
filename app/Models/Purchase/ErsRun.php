<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErsRun extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'organization_id',
        'run_date',
        'status',
        'processed_count',
        'error_count',
        'run_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'run_date'    => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ErsRunItem::class);
    }
}
