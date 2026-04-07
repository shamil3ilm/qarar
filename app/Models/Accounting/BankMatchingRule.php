<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class BankMatchingRule extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const MATCH_FIELD_DESCRIPTION = 'description';
    public const MATCH_FIELD_REFERENCE = 'reference';
    public const MATCH_FIELD_AMOUNT = 'amount';

    public const MATCH_TYPE_CONTAINS = 'contains';
    public const MATCH_TYPE_STARTS_WITH = 'starts_with';
    public const MATCH_TYPE_EQUALS = 'equals';
    public const MATCH_TYPE_REGEX = 'regex';

    public const ACTION_CATEGORIZE = 'categorize';
    public const ACTION_MATCH_CONTACT = 'match_contact';
    public const ACTION_MATCH_ACCOUNT = 'match_account';
    public const ACTION_EXCLUDE = 'exclude';

    public const TRANSACTION_TYPE_DEBIT = 'debit';
    public const TRANSACTION_TYPE_CREDIT = 'credit';

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'name',
        'match_field',
        'match_type',
        'match_value',
        'transaction_type',
        'action',
        'action_data',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'action_data' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Check if a bank transaction matches this rule.
     */
    public function matches(array $transactionData): bool
    {
        if ($this->transaction_type && isset($transactionData['transaction_type'])) {
            if ($this->transaction_type !== $transactionData['transaction_type']) {
                return false;
            }
        }

        $fieldValue = $transactionData[$this->match_field] ?? '';

        return match ($this->match_type) {
            self::MATCH_TYPE_CONTAINS => str_contains(strtolower((string) $fieldValue), strtolower($this->match_value)),
            self::MATCH_TYPE_STARTS_WITH => str_starts_with(strtolower((string) $fieldValue), strtolower($this->match_value)),
            self::MATCH_TYPE_EQUALS => strtolower((string) $fieldValue) === strtolower($this->match_value),
            self::MATCH_TYPE_REGEX => (bool) preg_match($this->match_value, (string) $fieldValue),
            default => false,
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    public function scopeForAccount($query, ?int $bankAccountId)
    {
        return $query->where(function ($q) use ($bankAccountId) {
            $q->whereNull('bank_account_id');
            if ($bankAccountId) {
                $q->orWhere('bank_account_id', $bankAccountId);
            }
        });
    }
}
