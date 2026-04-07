<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditExposure extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'snapshot_date'    => 'date',
            'open_invoices'    => 'decimal:4',
            'open_orders'      => 'decimal:4',
            'total_exposure'   => 'decimal:4',
            'credit_limit'     => 'decimal:4',
            'available_credit' => 'decimal:4',
            'utilization_pct'  => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isOverLimit(): bool
    {
        return (float) $this->available_credit < 0;
    }

    public function scopeLatestPerContact($query)
    {
        return $query->whereIn('id', function ($sub) {
            $sub->selectRaw('MAX(id)')
                ->from('credit_exposures')
                ->groupBy('organization_id', 'contact_id');
        });
    }
}
