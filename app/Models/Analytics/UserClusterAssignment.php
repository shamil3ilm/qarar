<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserClusterAssignment extends Model
{
    public $timestamps = false;

    protected $table = 'user_cluster_assignments';

    protected $fillable = [
        'user_id',
        'organization_id',
        'cluster_name',
        'algorithm',
        'confidence',
        'dimensions',
        'assigned_at',
        'expires_at',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
