<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDownloadToken extends Model
{
    use HasFactory;

    public const TYPE_BILL = 'bill';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_PAYSLIP = 'payslip';
    public const TYPE_RECEIPT = 'receipt';

    protected $fillable = [
        'access_count',
        'access_expires_at',
        'document_id',
        'document_type',
        'expires_at',
        'first_accessed_at',
        'generated_by',
        'is_revoked',
        'organization_id',
        'token',
    ];

    protected $casts = [
        'access_expires_at' => 'datetime',
        'expires_at' => 'datetime',
        'first_accessed_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    /**
     * Check whether this token is still usable.
     *
     * A token is valid when:
     *  - it has not been manually revoked,
     *  - its absolute expiry has not passed, and
     *  - if it was already accessed, the 24-hour access window has not closed.
     */
    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        if ($this->first_accessed_at !== null && $this->access_expires_at?->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Record a download access against this token.
     *
     * Increments the access counter. On first access, stamps first_accessed_at
     * and sets access_expires_at to 24 hours from now.
     */
    public function recordAccess(): void
    {
        $this->access_count += 1;

        if ($this->first_accessed_at === null) {
            $this->first_accessed_at = now();
            $this->access_expires_at = now()->addHours(24);
        }

        $this->save();
    }

    /**
     * Scope: tokens that have not yet expired or been revoked.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('is_revoked', false)
            ->where('expires_at', '>', now());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
