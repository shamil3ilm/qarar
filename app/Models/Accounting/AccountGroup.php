<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGroup extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'accounting_account_groups';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'account_category',
        'number_range_from',
        'number_range_to',
        'reconciliation_account',
        'reconciliation_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_account' => 'boolean',
            'is_active'              => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
