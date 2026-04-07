<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DunningBlock extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'blocked_until' => 'date',
            'released_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isActive(): bool
    {
        return $this->released_at === null
            && ($this->blocked_until === null || $this->blocked_until->isFuture());
    }

    public function scopeActive($query)
    {
        return $query->whereNull('released_at')
            ->where(function ($q) {
                $q->whereNull('blocked_until')
                    ->orWhere('blocked_until', '>=', now()->toDateString());
            });
    }
}
