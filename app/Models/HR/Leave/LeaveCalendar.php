<?php

declare(strict_types=1);

namespace App\Models\HR\Leave;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCalendar extends Model
{
    use HasFactory;

    protected $table = 'leave_calendar';

    protected $guarded = ['id'];
}