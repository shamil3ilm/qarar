<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmPositionTask extends Model
{
    use BelongsToOrganization, HasUuid;

    public const RESPONSIBILITY_LEVEL_PRIMARY    = 'primary';
    public const RESPONSIBILITY_LEVEL_SECONDARY  = 'secondary';
    public const RESPONSIBILITY_LEVEL_ADDITIONAL = 'additional';

    protected $fillable = [
        'organization_id',
        'position_id',
        'task_id',
        'responsibility_level',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(OmTask::class, 'task_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
