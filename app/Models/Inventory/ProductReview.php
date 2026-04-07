<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReview extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'product_id', 'contact_id', 'reviewer_name', 'rating',
        'title', 'review_text', 'pros', 'cons', 'is_verified_purchase', 'invoice_id',
        'status', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'pros' => 'array',
        'cons' => 'array',
        'is_verified_purchase' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sales\Contact::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
