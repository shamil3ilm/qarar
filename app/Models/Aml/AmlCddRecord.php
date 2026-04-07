<?php

declare(strict_types=1);

namespace App\Models\Aml;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmlCddRecord extends Model
{
    use HasFactory;

    // CDD level constants
    public const LEVEL_STANDARD   = 'standard';
    public const LEVEL_ENHANCED   = 'enhanced';
    public const LEVEL_SIMPLIFIED = 'simplified';

    // Status constants
    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'cdd_level',
        'status',
        'verification_data',
        'verified_at',
        'expires_at',
        'verified_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'verification_data' => 'array',
            'verified_at'       => 'date',
            'expires_at'        => 'date',
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

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
