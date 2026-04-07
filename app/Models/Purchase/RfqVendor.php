<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqVendor extends Model
{
    use HasFactory;

    protected $table = 'rfq_vendors';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'response_deadline' => 'date',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RfqHeader::class, 'rfq_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RfqQuote::class, 'rfq_vendor_id');
    }

    public function hasResponded(): bool
    {
        return in_array($this->status, ['responded', 'awarded'], true);
    }
}
