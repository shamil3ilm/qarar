<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\IpAllowlistRule;
use Illuminate\Support\Str;

class IpAllowlistService
{
    public function checkAccess(string $ip, int $orgId, string $context = 'all'): bool
    {
        $rules = IpAllowlistRule::where('organization_id', $orgId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('applies_to', 'all')->orWhere('applies_to', $context))
            ->orderBy('rule_type') // deny first
            ->get();

        if ($rules->isEmpty()) {
            return true; // no rules = allow all
        }

        foreach ($rules as $rule) {
            if ($this->ipMatchesRule($ip, $rule)) {
                return $rule->rule_type === 'allow';
            }
        }

        return true;
    }

    public function addRule(array $data): IpAllowlistRule
    {
        return IpAllowlistRule::create([
            'uuid'            => Str::uuid(),
            'organization_id' => $data['organization_id'],
            'rule_name'       => $data['rule_name'],
            'ip_address'      => $data['ip_address'] ?? null,
            'ip_range_start'  => $data['ip_range_start'] ?? null,
            'ip_range_end'    => $data['ip_range_end'] ?? null,
            'cidr_notation'   => $data['cidr_notation'] ?? null,
            'rule_type'       => $data['rule_type'] ?? 'allow',
            'applies_to'      => $data['applies_to'] ?? 'all',
            'role_id'         => $data['role_id'] ?? null,
            'active'          => $data['active'] ?? true,
            'created_by'      => $data['created_by'],
        ]);
    }

    public function isIpInRange(string $ip, string $start, string $end): bool
    {
        return ip2long($ip) >= ip2long($start) && ip2long($ip) <= ip2long($end);
    }

    public function isIpInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $mask    = -1 << (32 - (int) $bits);
        $subnet  = ip2long($subnet) & $mask;
        $ipLong  = ip2long($ip);
        return ($ipLong & $mask) === $subnet;
    }

    private function ipMatchesRule(string $ip, IpAllowlistRule $rule): bool
    {
        if ($rule->ip_address && $rule->ip_address === $ip) {
            return true;
        }
        if ($rule->cidr_notation && $this->isIpInCidr($ip, $rule->cidr_notation)) {
            return true;
        }
        if ($rule->ip_range_start && $rule->ip_range_end) {
            return $this->isIpInRange($ip, $rule->ip_range_start, $rule->ip_range_end);
        }
        return false;
    }
}
