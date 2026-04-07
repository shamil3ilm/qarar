<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPolicy extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'document_type',
        'policy_name',
        'retention_years',
        'jurisdiction',
        'action_on_expiry',
        'legal_hold_override',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'retention_years'     => 'integer',
            'legal_hold_override' => 'boolean',
            'is_active'           => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
