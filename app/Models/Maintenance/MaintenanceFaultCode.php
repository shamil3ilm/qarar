<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceFaultCode extends Model
{
    protected $table = 'maintenance_fault_codes';

    protected $fillable = [
        'organization_id',
        'code',
        'description',
        'fault_type',
        'cause',
        'recommended_action',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rcaRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRca::class, 'fault_code_id');
    }
}
