<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierNcrRecord extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'supplier_ncr_records';

    protected $fillable = [
        'uuid', 'organization_id', 'ncr_number', 'supplier_id', 'product_id',
        'po_number', 'nonconformance_description', 'severity', 'disposition',
        'status', 'detected_date', 'closed_date',
    ];

    protected $casts = [
        'detected_date' => 'date',
        'closed_date'   => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
