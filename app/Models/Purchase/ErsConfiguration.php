<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErsConfiguration extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'is_enabled',
        'auto_post',
        'tolerance_percent',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'        => 'boolean',
            'auto_post'         => 'boolean',
            'tolerance_percent' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }
}
