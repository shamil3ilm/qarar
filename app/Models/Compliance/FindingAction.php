<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingAction extends Model
{
    use HasFactory;

    protected $table = 'grc_finding_actions';

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_OVERDUE     = 'overdue';

    protected $fillable = [
        'finding_id',
        'title',
        'description',
        'assigned_to',
        'due_date',
        'status',
        'completed_at',
        'completion_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'finding_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
