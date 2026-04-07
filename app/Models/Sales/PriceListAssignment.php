<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListAssignment extends Model
{
    public const TYPE_CONTACT = 'contact';
    public const TYPE_CUSTOMER_GROUP = 'customer_group';
    public const TYPE_ALL = 'all';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'assignment_id' => 'integer',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'assignment_id');
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'assignment_id');
    }
}
