<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessPartnerRole extends Model
{
    protected $table = 'business_partner_roles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
            'is_active'  => 'boolean',
        ];
    }

    public function businessPartner(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'business_partner_id');
    }
}
