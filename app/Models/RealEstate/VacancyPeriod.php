<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacancyPeriod extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 're_vacancy_periods';

    protected $guarded = ['id'];

    public const REASON_LEASE_EXPIRED       = 'lease_expired';
    public const REASON_EARLY_TERMINATION   = 'early_termination';
    public const REASON_NEW_UNIT            = 'new_unit';
    public const REASON_RENOVATION          = 'renovation';
    public const REASON_OWNER_USE           = 'owner_use';

    protected function casts(): array
    {
        return [
            'vacant_from'   => 'date',
            'vacant_to'     => 'date',
            'market_rent'   => 'decimal:2',
            'vacancy_loss'  => 'decimal:2',
        ];
    }

    public function rentalUnit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class, 'rental_unit_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function isOpen(): bool
    {
        return $this->vacant_to === null;
    }

    public function getDaysVacant(): int
    {
        $end = $this->vacant_to ?? now()->toDateObject();
        return $this->vacant_from->diffInDays($end);
    }

    public function computeVacancyLoss(): float
    {
        if (! $this->market_rent || $this->market_rent <= 0) {
            return 0.0;
        }

        $dailyRent = (float) $this->market_rent / 30;
        return round($dailyRent * $this->getDaysVacant(), 2);
    }
}
