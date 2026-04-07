<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrcControl extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'grc_control_library';

    // Control type constants
    public const TYPE_PREVENTIVE = 'preventive';
    public const TYPE_DETECTIVE  = 'detective';
    public const TYPE_CORRECTIVE = 'corrective';
    public const TYPE_DIRECTIVE  = 'directive';

    // Control category constants
    public const CATEGORY_IT             = 'it';
    public const CATEGORY_MANUAL         = 'manual';
    public const CATEGORY_AUTOMATED      = 'automated';
    public const CATEGORY_SEMI_AUTOMATED = 'semi_automated';

    // Status constants
    public const STATUS_ACTIVE       = 'active';
    public const STATUS_INACTIVE     = 'inactive';
    public const STATUS_UNDER_REVIEW = 'under_review';

    protected $fillable = [
        'organization_id',
        'control_code',
        'title',
        'description',
        'control_type',
        'control_category',
        'module_reference',
        'frequency',
        'status',
        'control_owner_id',
        'created_by',
    ];

    public function controlOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'control_owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
