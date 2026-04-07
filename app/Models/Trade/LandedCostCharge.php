<?php

declare(strict_types=1);

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostCharge extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}