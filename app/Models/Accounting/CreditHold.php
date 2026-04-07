<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditHold extends Model
{
    use HasFactory, HasAuditTrail, HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'held_at'     => 'datetime',
            'released_at' => 'datetime',
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

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('released_at');
    }
}
