<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Accounting\Account;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRevenueRecognition extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'uuid', 'organization_id', 'project_id', 'period_year', 'period_month',
        'recognized_revenue', 'recognized_cost', 'completion_percentage',
        'gl_account_id', 'posted_at',
    ];

    protected $casts = [
        'recognized_revenue'     => 'decimal:4',
        'recognized_cost'        => 'decimal:4',
        'completion_percentage'  => 'decimal:2',
        'posted_at'              => 'datetime',
    ];

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }
}
