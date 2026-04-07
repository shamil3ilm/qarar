<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionScheduleRun extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'run_at',
        'documents_evaluated',
        'documents_archived',
        'documents_deleted',
        'documents_skipped_legal_hold',
        'run_log',
    ];

    protected function casts(): array
    {
        return [
            'run_at'                       => 'datetime',
            'documents_evaluated'          => 'integer',
            'documents_archived'           => 'integer',
            'documents_deleted'            => 'integer',
            'documents_skipped_legal_hold' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
