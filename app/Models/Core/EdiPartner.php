<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EdiPartner extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public const TYPE_VENDOR   = 'vendor';
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_BANK     = 'bank';
    public const TYPE_CARRIER  = 'carrier';
    public const TYPE_OTHER    = 'other';

    public const STANDARD_EDIFACT = 'edifact';
    public const STANDARD_X12     = 'x12';
    public const STANDARD_UBL     = 'ubl';
    public const STANDARD_IDOC    = 'idoc';
    public const STANDARD_CUSTOM  = 'custom';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'test_mode' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EdiMessage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('partner_type', $type);
    }
}
