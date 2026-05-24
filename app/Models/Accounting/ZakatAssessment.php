<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zakat Assessment (SAP GAZT / ZATCA equivalent — Saudi annual Zakat filing).
 */
class ZakatAssessment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'zakat_assessments';

    protected $guarded = ['id'];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ASSESSED  = 'assessed';
    public const STATUS_PAID      = 'paid';

    /** SAP Zakat rate: 2.5% of Zakat base */
    public const ZAKAT_RATE = 2.5;

    protected function casts(): array
    {
        return [
            'total_assets'           => 'decimal:4',
            'total_liabilities'      => 'decimal:4',
            'non_zakatable_assets'   => 'decimal:4',
            'zakat_base'             => 'decimal:4',
            'zakat_rate'             => 'decimal:4',
            'zakat_due'              => 'decimal:4',
            'saudi_ownership_pct'    => 'decimal:4',
            'zakat_paid'             => 'decimal:4',
            'zakat_remaining'        => 'decimal:4',
            'filing_due_date'        => 'date',
            'filed_at'               => 'date',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Outstanding Zakat payable (due - paid).
     */
    public function outstandingBalance(): float
    {
        return max(0.0, (float) bcsub((string) $this->zakat_due, (string) $this->zakat_paid, 4));
    }
}
