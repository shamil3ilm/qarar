<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DpsListEntry extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'dps_list_entries';

    public const ENTRY_PERSON   = 'person';
    public const ENTRY_ENTITY   = 'entity';
    public const ENTRY_VESSEL   = 'vessel';
    public const ENTRY_AIRCRAFT = 'aircraft';

    protected $fillable = [
        'dps_sanction_list_id',
        'entry_type',
        'name',
        'aliases',
        'country_code',
        'address',
        'id_number',
        'program',
        'remarks',
        'effective_date',
        'expiry_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'aliases'        => 'array',
            'effective_date' => 'date',
            'expiry_date'    => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function sanctionList(): BelongsTo
    {
        return $this->belongsTo(DpsSanctionList::class, 'dps_sanction_list_id');
    }

    /**
     * Return all searchable name strings: primary name + aliases.
     *
     * @return string[]
     */
    public function getAllNames(): array
    {
        $names = [$this->name];

        if (is_array($this->aliases)) {
            $names = array_merge($names, $this->aliases);
        }

        return array_filter($names);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
