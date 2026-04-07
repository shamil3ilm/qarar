<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrcCsaQuestionnaire extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'grc_csa_questionnaires';

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_PUBLISHED   = 'published';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_REVIEWED    = 'reviewed';

    public const AREA_FINANCIAL_REPORTING = 'financial_reporting';
    public const AREA_IT_GENERAL          = 'it_general';
    public const AREA_OPERATIONAL         = 'operational';
    public const AREA_COMPLIANCE          = 'compliance';
    public const AREA_FRAUD_PREVENTION    = 'fraud_prevention';

    protected $fillable = [
        'organization_id',
        'questionnaire_number',
        'title',
        'description',
        'control_area',
        'due_date',
        'status',
        'owner_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(GrcCsaQuestion::class, 'questionnaire_id')->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(GrcCsaResponse::class, 'questionnaire_id');
    }
}
