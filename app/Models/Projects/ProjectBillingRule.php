<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectBillingRule extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'project_id', 'billing_type', 'currency',
        'customer_id', 'total_contract_value', 'retention_percentage',
    ];

    protected $casts = [
        'total_contract_value' => 'decimal:4',
        'retention_percentage' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectBillingMilestone::class)->orderBy('due_date');
    }
}
