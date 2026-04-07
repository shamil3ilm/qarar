<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLegalHold extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'document_type',
        'document_id',
        'hold_reason',
        'held_by',
        'hold_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hold_until' => 'date',
            'is_active'  => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function heldByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }
}
