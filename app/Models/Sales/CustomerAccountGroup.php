<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerAccountGroup extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'group_code',
        'description',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'customer_account_group_id');
    }
}
