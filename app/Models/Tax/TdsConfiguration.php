<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TdsConfiguration extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $table = 'tds_configurations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'threshold_amount'   => 'decimal:2',
            'rate_resident'      => 'decimal:2',
            'rate_non_resident'  => 'decimal:2',
            'is_active'          => 'boolean',
        ];
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(TdsDeduction::class, 'tds_section_id');
    }
}
