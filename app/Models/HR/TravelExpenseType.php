<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelExpenseType extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'travel_expense_types';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'category',
        'daily_limit',
        'gl_account_code',
        'requires_receipt',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'daily_limit'      => 'decimal:4',
            'requires_receipt' => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public const CATEGORY_ACCOMMODATION = 'accommodation';
    public const CATEGORY_TRANSPORT     = 'transport';
    public const CATEGORY_MEALS         = 'meals';
    public const CATEGORY_ENTERTAINMENT = 'entertainment';
    public const CATEGORY_OTHER         = 'other';

    public function reportLines(): HasMany
    {
        return $this->hasMany(TravelExpenseReportLine::class, 'expense_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
