<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprConsentRecord extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'uuid', 'organization_id', 'contact_id', 'user_id', 'purpose',
        'consent_given', 'given_at', 'withdrawn_at', 'ip_address', 'consent_text',
    ];

    protected $casts = [
        'consent_given' => 'boolean',
        'given_at'      => 'datetime',
        'withdrawn_at'  => 'datetime',
    ];

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
