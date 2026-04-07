<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EwmStorageSection extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'ewm_storage_sections';

    public const VELOCITY_A = 'A';  // fast-moving
    public const VELOCITY_B = 'B';
    public const VELOCITY_C = 'C';
    public const VELOCITY_D = 'D';  // slow-moving

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function storageType(): BelongsTo
    {
        return $this->belongsTo(EwmStorageType::class, 'storage_type_id');
    }

    public function bins(): HasMany
    {
        return $this->hasMany(EwmBin::class, 'storage_section_id');
    }
}
