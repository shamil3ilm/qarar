<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GstReturn extends Model
{
    use HasUuid;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $table = 'gst_returns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_taxable_value' => 'decimal:2',
            'total_cgst'          => 'decimal:2',
            'total_sgst'          => 'decimal:2',
            'total_igst'          => 'decimal:2',
            'total_cess'          => 'decimal:2',
            'filed_at'            => 'datetime',
        ];
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFiled(): bool
    {
        return in_array($this->status, ['filed', 'late_filed'], true);
    }
}
