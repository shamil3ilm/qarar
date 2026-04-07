<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeatureUsage extends Model
{
    public $timestamps = false;

    protected $table = 'user_feature_usage';

    protected $fillable = [
        'user_id',
        'organization_id',
        'module',
        'feature',
        'usage_date',
        'access_count',
        'create_count',
        'update_count',
        'delete_count',
        'total_duration_ms',
    ];

    protected $casts = [
        'usage_date' => 'date',
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
