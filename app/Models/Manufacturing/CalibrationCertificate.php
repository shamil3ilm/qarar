<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationCertificate extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'calibration_order_id',
        'certificate_number',
        'issued_date',
        'valid_until',
        'issued_by',
        'accreditation_body',
        'certificate_data',
    ];

    protected function casts(): array
    {
        return [
            'issued_date'      => 'date',
            'valid_until'      => 'date',
            'certificate_data' => 'array',
        ];
    }

    public function calibrationOrder(): BelongsTo
    {
        return $this->belongsTo(CalibrationOrder::class);
    }

    public function isValid(): bool
    {
        return $this->valid_until >= now()->toDateString();
    }
}
