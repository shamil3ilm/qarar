<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlagTarget extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enabled'    => 'boolean',
        'percentage' => 'integer',
    ];

    public const TYPE_USER       = 'user';
    public const TYPE_BRANCH     = 'branch';
    public const TYPE_ROLE       = 'role';
    public const TYPE_PERCENTAGE = 'percentage';

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
