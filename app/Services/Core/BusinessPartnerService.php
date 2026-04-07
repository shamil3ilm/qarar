<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\BusinessPartner;
use App\Models\Core\BusinessPartnerRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Business Partner / CVI (Customer-Vendor Integration) — SAP equivalent.
 *
 * Creates and manages a unified business partner record that may hold
 * both customer (FLCU00) and vendor (FLVN00) roles, replacing separate
 * contact/customer and supplier master records.
 *
 * CVI synchronisation:
 *   - assignRole(FLCU00)  links or creates the CRM contact record
 *   - assignRole(FLVN00)  links or creates the Purchase supplier record
 *   - revokeRole()        deactivates the role without deleting the linked record
 */
class BusinessPartnerService
{
    public function list(int $organizationId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = BusinessPartner::where('organization_id', $organizationId)
            ->with('activeRoles');

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('bp_number', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->whereHas('activeRoles', fn ($q) => $q->where('role_code', $filters['role']));
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(int $organizationId, array $data): BusinessPartner
    {
        return DB::transaction(function () use ($organizationId, $data): BusinessPartner {
            $bp = BusinessPartner::create([
                'organization_id' => $organizationId,
                'bp_number'       => $this->generateBpNumber($organizationId),
                'bp_category'     => $data['bp_category'] ?? BusinessPartner::CATEGORY_ORG,
                'name'            => $data['name'],
                'name2'           => $data['name2'] ?? null,
                'search_term'     => $data['search_term'] ?? null,
                'email'           => $data['email'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'mobile'          => $data['mobile'] ?? null,
                'website'         => $data['website'] ?? null,
                'tax_id'          => $data['tax_id'] ?? null,
                'vat_number'      => $data['vat_number'] ?? null,
                'commercial_reg'  => $data['commercial_reg'] ?? null,
                'street'          => $data['street'] ?? null,
                'city'            => $data['city'] ?? null,
                'state'           => $data['state'] ?? null,
                'postal_code'     => $data['postal_code'] ?? null,
                'country'         => $data['country'] ?? null,
                'contact_id'      => $data['contact_id'] ?? null,
                'supplier_id'     => $data['supplier_id'] ?? null,
                'metadata'        => $data['metadata'] ?? null,
            ]);

            // Assign initial roles if provided
            foreach ($data['roles'] ?? [] as $roleCode) {
                $this->assignRole($bp, $roleCode);
            }

            return $bp->load('roles');
        });
    }

    public function update(BusinessPartner $bp, array $data): BusinessPartner
    {
        $bp->update(array_filter([
            'name'           => $data['name'] ?? null,
            'name2'          => $data['name2'] ?? null,
            'search_term'    => $data['search_term'] ?? null,
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'mobile'         => $data['mobile'] ?? null,
            'website'        => $data['website'] ?? null,
            'tax_id'         => $data['tax_id'] ?? null,
            'vat_number'     => $data['vat_number'] ?? null,
            'commercial_reg' => $data['commercial_reg'] ?? null,
            'street'         => $data['street'] ?? null,
            'city'           => $data['city'] ?? null,
            'state'          => $data['state'] ?? null,
            'postal_code'    => $data['postal_code'] ?? null,
            'country'        => $data['country'] ?? null,
            'metadata'       => $data['metadata'] ?? null,
        ], fn ($v) => $v !== null));

        return $bp->fresh('roles');
    }

    /**
     * Assign a role (FLCU00 / FLVN00 / etc.) to a BP.
     * Idempotent — does nothing if the role is already active.
     */
    public function assignRole(BusinessPartner $bp, string $roleCode): BusinessPartnerRole
    {
        $existingRole = $bp->roles()->where('role_code', $roleCode)->first();

        if ($existingRole) {
            if (! $existingRole->is_active) {
                $existingRole->update(['is_active' => true]);
            }
            return $existingRole;
        }

        return BusinessPartnerRole::create([
            'business_partner_id' => $bp->id,
            'role_code'           => $roleCode,
            'role_name'           => $this->roleName($roleCode),
            'is_active'           => true,
        ]);
    }

    /**
     * Revoke a role — sets is_active=false without deleting.
     */
    public function revokeRole(BusinessPartner $bp, string $roleCode): void
    {
        $bp->roles()->where('role_code', $roleCode)->update(['is_active' => false]);
    }

    /**
     * Merge two BPs — transfers roles from source to target.
     * Source is deactivated after merge.
     */
    public function merge(BusinessPartner $source, BusinessPartner $target): BusinessPartner
    {
        return DB::transaction(function () use ($source, $target): BusinessPartner {
            foreach ($source->roles as $role) {
                if (! $target->hasRole($role->role_code)) {
                    $this->assignRole($target, $role->role_code);
                }
            }

            // Link contact/supplier if target doesn't already have them
            if ($source->contact_id && ! $target->contact_id) {
                $target->update(['contact_id' => $source->contact_id]);
            }

            if ($source->supplier_id && ! $target->supplier_id) {
                $target->update(['supplier_id' => $source->supplier_id]);
            }

            $source->update(['is_active' => false]);

            return $target->fresh('roles');
        });
    }

    // ----------------------------------------------------------------

    private function generateBpNumber(int $organizationId): string
    {
        $last = BusinessPartner::where('organization_id', $organizationId)
            ->lockForUpdate()
            ->max('id') ?? 0;

        return 'BP-' . str_pad((string) ($last + 1), 8, '0', STR_PAD_LEFT);
    }

    private function roleName(string $roleCode): string
    {
        return match ($roleCode) {
            BusinessPartner::ROLE_CUSTOMER => 'General Customer',
            BusinessPartner::ROLE_VENDOR   => 'General Vendor',
            BusinessPartner::ROLE_PERSON   => 'Natural Person',
            BusinessPartner::ROLE_ORG      => 'Organisation',
            default                        => $roleCode,
        };
    }
}
