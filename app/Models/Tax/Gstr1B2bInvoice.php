<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gstr1B2bInvoice extends Model
{
    use HasFactory;

    protected $table = 'gstr1_b2b_invoices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'invoice_date'   => 'date',
            'invoice_value'  => 'decimal:4',
            'taxable_value'  => 'decimal:4',
            'igst'           => 'decimal:4',
            'cgst'           => 'decimal:4',
            'sgst'           => 'decimal:4',
            'cess'           => 'decimal:4',
        ];
    }

    public function gstr1Return(): BelongsTo
    {
        return $this->belongsTo(Gstr1Return::class, 'gstr1_return_id');
    }
}
