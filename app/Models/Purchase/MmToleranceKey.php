<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MmToleranceKey extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'tolerance_key',
        'description',
    ];

    public function checkResults(): HasMany
    {
        return $this->hasMany(MmToleranceCheckResult::class, 'tolerance_key_id');
    }
}
