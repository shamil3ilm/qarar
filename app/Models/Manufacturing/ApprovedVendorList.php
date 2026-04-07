<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovedVendorList extends Model
{
    use HasUuid;

    protected $table = 'approved_vendor_lists';

    protected $fillable = [
        'uuid', 'organization_id', 'supplier_id', 'product_id',
        'approved_date', 'expiry_date', 'status', 'approval_conditions',
    ];

    protected $casts = [
        'approved_date' => 'date',
        'expiry_date'   => 'date',
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
