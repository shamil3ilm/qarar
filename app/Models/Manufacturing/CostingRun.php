<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostingRun extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'run_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    // Relationships

    public function costingVersion(): BelongsTo
    {
        return $this->belongsTo(CostingVersion::class, 'costing_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helpers

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getSuccessRate(): float
    {
        $total = $this->products_processed + $this->products_failed;
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->products_processed / $total) * 100, 2);
    }
}
