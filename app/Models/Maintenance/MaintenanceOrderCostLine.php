<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Accounting\CostElement;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceOrderCostLine extends Model
{
    use BelongsToOrganization, HasUuid;

    public const TYPE_LABOR    = 'labor';
    public const TYPE_MATERIAL = 'material';
    public const TYPE_EXTERNAL = 'external';
    public const TYPE_OVERHEAD = 'overhead';

    protected $fillable = [
        'organization_id',
        'maintenance_order_id',
        'cost_element_id',
        'cost_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'currency_code',
        'posting_date',
        'vendor_id',
        'employee_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:4',
            'unit_cost'    => 'decimal:4',
            'total_cost'   => 'decimal:4',
            'posting_date' => 'date',
        ];
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
