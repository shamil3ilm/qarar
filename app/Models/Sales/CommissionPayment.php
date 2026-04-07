<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Payslip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionPayment extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PROCESSED = 'processed';

    protected $fillable = [
        'organization_id',
        'payment_reference',
        'sales_rep_id',
        'period_year',
        'period_month',
        'total_amount',
        'currency',
        'payment_date',
        'status',
        'payslip_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:4',
        'payment_date' => 'date',
    ];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
