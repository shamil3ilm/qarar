<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'first_payment_date' => 'date',
            'maturity_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'total_interest' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'emi_amount' => 'decimal:2',
            'deduct_from_payroll' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->loan_number)) {
                $model->loan_number = static::generateNumber($model->organization_id);
            }
        });
    }

    public static function generateNumber(int $organizationId): string
    {
        $year = now()->format('Y');
        $key = "LN-{$year}-";

        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('loan_number', 'like', "{$key}%")
            ->orderByDesc('id')
            ->value('loan_number');

        $sequence = $last ? (int) substr($last, strlen($key)) + 1 : 1;

        return $key . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'loan_account_id');
    }

    public function interestAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'interest_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Loan types
    public const TYPE_EMPLOYEE_LOAN = 'employee_loan';
    public const TYPE_BUSINESS_LOAN = 'business_loan';
    public const TYPE_MORTGAGE = 'mortgage';
    public const TYPE_VEHICLE = 'vehicle';
    public const TYPE_OTHER = 'other';

    // Loan categories
    public const CATEGORY_PERSONAL = 'personal';
    public const CATEGORY_BUSINESS = 'business';
    public const CATEGORY_EQUIPMENT = 'equipment';
    public const CATEGORY_REAL_ESTATE = 'real_estate';

    // Interest types
    public const INTEREST_TYPE_SIMPLE = 'simple';
    public const INTEREST_TYPE_COMPOUND = 'compound';
    public const INTEREST_TYPE_FLAT = 'flat';

    // Payment frequencies
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_BI_WEEKLY = 'bi-weekly';
    public const FREQUENCY_MONTHLY = 'monthly';

    // Lender types
    public const LENDER_ORGANIZATION = 'organization';
    public const LENDER_BANK = 'bank';
    public const LENDER_OTHER = 'other';

    // Status values
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DEFAULTED = 'defaulted';
    public const STATUS_WRITTEN_OFF = 'written_off';

    // Approval status values
    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';
}