<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GdprDataSubjectRequest extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'request_type', 'requester_name', 'requester_email',
        'requester_id', 'status', 'received_at', 'deadline_at', 'completed_at',
        'rejection_reason', 'data_exported_path',
    ];

    protected $casts = [
        'received_at'  => 'datetime',
        'deadline_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
