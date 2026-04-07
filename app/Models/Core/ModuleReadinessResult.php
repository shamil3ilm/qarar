<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleReadinessResult extends Model
{
    use HasFactory;
    use HasUuid;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'results'    => 'array',
        'run_at'     => 'datetime',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }
}
