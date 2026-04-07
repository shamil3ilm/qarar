<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstallmentPlan extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const DOC_INVOICE     = 'invoice';
    public const DOC_BILL        = 'bill';
    public const DOC_SALES_ORDER = 'sales_order';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_amount'      => 'decimal:4',
            'total_paid'        => 'decimal:4',
            'outstanding'       => 'decimal:4',
            'installment_count' => 'integer',
            'start_date'        => 'date',
            'end_date'          => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class)->orderBy('installment_number');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function nextDueSchedule(): ?InstallmentSchedule
    {
        return $this->schedules()
            ->whereIn('status', [InstallmentSchedule::STATUS_PENDING, InstallmentSchedule::STATUS_OVERDUE])
            ->orderBy('due_date')
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOverdue($query)
    {
        return $query->whereHas('schedules', function ($q): void {
            $q->where('status', InstallmentSchedule::STATUS_OVERDUE);
        });
    }
}
