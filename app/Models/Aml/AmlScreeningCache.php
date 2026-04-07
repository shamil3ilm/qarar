<?php

declare(strict_types=1);

namespace App\Models\Aml;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmlScreeningCache extends Model
{
    use HasFactory;

    protected $table = 'aml_screening_cache';

    public $timestamps = false;

    // List type constants
    public const LIST_OFAC  = 'ofac';
    public const LIST_EU    = 'eu';
    public const LIST_UN    = 'un';
    public const LIST_PEP   = 'pep';
    public const LIST_LOCAL = 'local';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'list_type',
        'is_match',
        'match_details',
        'data_hash',
        'screened_at',
    ];

    protected function casts(): array
    {
        return [
            'is_match'     => 'boolean',
            'match_details' => 'array',
            'screened_at'  => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    // Relationships
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
