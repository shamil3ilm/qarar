<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelActionStep extends Model
{
    protected $table = 'personnel_action_steps';

    protected $guarded = ['id'];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_SKIPPED   = 'skipped';

    protected function casts(): array
    {
        return [
            'result'      => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function personnelAction(): BelongsTo
    {
        return $this->belongsTo(PersonnelAction::class, 'personnel_action_id');
    }
}
