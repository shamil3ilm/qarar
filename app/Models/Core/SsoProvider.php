<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SsoProvider extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'provider_name', 'protocol', 'client_id',
        'client_secret_encrypted', 'authorization_endpoint', 'token_endpoint',
        'userinfo_endpoint', 'saml_entity_id', 'saml_sso_url', 'saml_certificate',
        'attribute_mapping', 'active',
    ];

    protected $casts = [
        'attribute_mapping' => 'array',
        'active'            => 'boolean',
    ];

    protected $hidden = ['client_secret_encrypted'];

    public function sessions(): HasMany
    {
        return $this->hasMany(SsoSession::class, 'provider_id');
    }
}
