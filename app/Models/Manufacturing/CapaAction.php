<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapaAction extends Model
{
    use HasUuid;

    protected $table = 'capa_actions';

    protected $fillable = [
        'uuid', 'capa_record_id', 'action_number', 'description',
        'assigned_to_id', 'due_date', 'completed_date', 'status', 'completion_notes',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'completed_date' => 'date',
    ];

    public function capaRecord(): BelongsTo
    {
        return $this->belongsTo(CapaRecord::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
}
