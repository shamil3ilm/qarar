<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class DigitalSignature extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid;

    // Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SIGNED,
        self::STATUS_DECLINED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'organization_id',
        'document_id',
        'signer_id',
        'signer_email',
        'signer_name',
        'status',
        'signature_data',
        'ip_address',
        'user_agent',
        'signed_at',
        'expires_at',
        'verification_code',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // Relationships

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSigned($query)
    {
        return $query->where('status', self::STATUS_SIGNED);
    }

    public function scopeForDocument($query, int $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopeForSigner($query, string $email)
    {
        return $query->where('signer_email', $email);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    // Helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function markAsSigned(string $signatureData, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->update([
            'status' => self::STATUS_SIGNED,
            'signature_data' => $signatureData,
            'signed_at' => now(),
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
        ]);
    }
}
