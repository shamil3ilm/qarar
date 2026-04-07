<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessageAttachment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}