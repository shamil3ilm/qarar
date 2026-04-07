<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessPartner extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'business_partners';

    protected $guarded = ['id'];

    // SAP BP role codes
    public const ROLE_CUSTOMER = 'FLCU00';
    public const ROLE_VENDOR   = 'FLVN00';
    public const ROLE_PERSON   = 'BUP001';
    public const ROLE_ORG      = 'BUP002';

    public const CATEGORY_ORG    = 'ORG';
    public const CATEGORY_PERSON = 'PERSON';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata'  => 'array',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(BusinessPartnerRole::class, 'business_partner_id');
    }

    public function activeRoles(): HasMany
    {
        return $this->hasMany(BusinessPartnerRole::class, 'business_partner_id')
            ->where('is_active', true);
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()->where('role_code', $roleCode)->where('is_active', true)->exists();
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(self::ROLE_CUSTOMER);
    }

    public function isVendor(): bool
    {
        return $this->hasRole(self::ROLE_VENDOR);
    }
}
