<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XbrlFilingElement extends Model
{
    protected $guarded = ['id'];

    public function filing(): BelongsTo
    {
        return $this->belongsTo(XbrlFiling::class, 'xbrl_filing_id');
    }

    /**
     * Return the value cast to float if it is numeric, otherwise as string.
     */
    public function getTypedValue(): float|string
    {
        return is_numeric($this->value) ? (float) $this->value : $this->value;
    }
}
