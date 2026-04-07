<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'code',
        'max_uses',
        'times_used',
        'assigned_to_contact_id',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function assignedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'assigned_to_contact_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class, 'coupon_code_id');
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->times_used >= $this->max_uses) {
            return false;
        }

        return $this->promotion && $this->promotion->isActive();
    }

    public function recordUsage(): void
    {
        $this->increment('times_used');

        if ($this->max_uses && $this->times_used >= $this->max_uses) {
            $this->update(['is_active' => false]);
        }
    }

    public function getRemainingUses(): ?int
    {
        if (! $this->max_uses) {
            return null;
        }

        return max(0, $this->max_uses - $this->times_used);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }
}
