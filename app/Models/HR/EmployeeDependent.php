<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDependent extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const RELATIONSHIP_SPOUSE  = 'spouse';
    public const RELATIONSHIP_CHILD   = 'child';
    public const RELATIONSHIP_PARENT  = 'parent';
    public const RELATIONSHIP_SIBLING = 'sibling';
    public const RELATIONSHIP_OTHER   = 'other';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'relationship',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'nationality',
        'id_type',
        'id_number',
        'id_expiry_date',
        'is_beneficiary',
        'is_sponsored',
        'visa_number',
        'visa_expiry_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'  => 'date',
            'id_expiry_date' => 'date',
            'visa_expiry_date' => 'date',
            'is_beneficiary' => 'boolean',
            'is_sponsored'   => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeBeneficiaries($query)
    {
        return $query->where('is_beneficiary', true);
    }

    public function scopeSponsored($query)
    {
        return $query->where('is_sponsored', true);
    }
}
