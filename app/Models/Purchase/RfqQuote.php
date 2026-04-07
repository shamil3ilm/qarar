<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqQuote extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'rfq_quotes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'total_amount' => 'decimal:4',
            'delivery_days' => 'integer',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RfqHeader::class, 'rfq_id');
    }

    public function rfqVendor(): BelongsTo
    {
        return $this->belongsTo(RfqVendor::class, 'rfq_vendor_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RfqQuoteLine::class, 'rfq_quote_id');
    }

    public function isAwarded(): bool
    {
        return $this->status === 'awarded';
    }

    public function canBeAwarded(): bool
    {
        return in_array($this->status, ['received', 'evaluated'], true);
    }
}
