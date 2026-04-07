<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_SUPPLIER = 'supplier';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'wallet_type',
        'currency_code',
        'balance',
        'credit_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function credit(float $amount, string $description, ?string $sourceType = null, ?int $sourceId = null): WalletTransaction
    {
        $balanceBefore = (float) $this->balance;
        $this->balance = bcadd((string) $this->balance, (string) $amount, 2);
        $this->save();

        return $this->transactions()->create([
            'transaction_type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => (float) $this->balance,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    public function debit(float $amount, string $description, ?string $sourceType = null, ?int $sourceId = null): WalletTransaction
    {
        $balanceBefore = (float) $this->balance;
        $this->balance = bcsub((string) $this->balance, (string) $amount, 2);
        $this->save();

        return $this->transactions()->create([
            'transaction_type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => (float) $this->balance,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    public function hasBalance(float $amount = 0): bool
    {
        return bccomp((string) $this->balance, (string) $amount, 2) >= 0;
    }

    public function getAvailableCredit(): float
    {
        if ($this->credit_limit <= 0) {
            return (float) $this->balance;
        }

        return (float) bcadd((string) $this->balance, (string) $this->credit_limit, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('wallet_type', $type);
    }
}
