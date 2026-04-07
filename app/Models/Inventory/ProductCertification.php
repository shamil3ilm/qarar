<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'certification_name', 'certification_body', 'certificate_number',
        'issued_date', 'expiry_date', 'certificate_file_path', 'status',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
