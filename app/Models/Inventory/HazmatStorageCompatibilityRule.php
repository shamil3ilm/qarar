<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HazmatStorageCompatibilityRule extends Model
{
    use BelongsToOrganization;

    protected $table = 'hazmat_storage_compatibility_rules';

    protected $fillable = [
        'organization_id',
        'storage_class_a_id',
        'storage_class_b_id',
        'is_compatible',
        'restriction_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_compatible' => 'boolean',
        ];
    }

    public function storageClassA(): BelongsTo
    {
        return $this->belongsTo(HazmatStorageClass::class, 'storage_class_a_id');
    }

    public function storageClassB(): BelongsTo
    {
        return $this->belongsTo(HazmatStorageClass::class, 'storage_class_b_id');
    }
}
