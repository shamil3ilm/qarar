<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionsWorklist extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $table = 'collections_worklist';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_overdue'        => 'decimal:4',
            'promise_amount'       => 'decimal:4',
            'overdue_days_max'     => 'integer',
            'promise_to_pay_date'  => 'date',
            'last_contact_at'      => 'datetime',
        ];
    }

    public const STATUS_NEW           = 'new';
    public const STATUS_CONTACTED     = 'contacted';
    public const STATUS_PROMISE_TO_PAY = 'promise_to_pay';
    public const STATUS_PAYMENT_PLAN  = 'payment_plan';
    public const STATUS_LEGAL         = 'legal';
    public const STATUS_WRITTEN_OFF   = 'written_off';

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('collections_status', [self::STATUS_WRITTEN_OFF]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('total_overdue', '>', 0);
    }
}
