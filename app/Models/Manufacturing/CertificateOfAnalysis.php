<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateOfAnalysis extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'certificates_of_analysis';

    protected $fillable = [
        'organization_id',
        'certificate_number',
        'product_id',
        'batch_number',
        'inspection_lot_id',
        'contact_id',
        'issue_date',
        'test_date',
        'test_results',
        'overall_result',
        'remarks',
        'issued_by',
        'approved_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'test_results' => 'array',
            'issue_date'   => 'date',
            'test_date'    => 'date',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
