<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ewaybill extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'ewaybills';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'generated_at'  => 'datetime',
            'valid_until'   => 'datetime',
            'cancelled_at'  => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'generated'
            && ($this->valid_until === null || $this->valid_until->isFuture());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->valid_until !== null && $this->valid_until->isPast());
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'generated')
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            });
    }
}
