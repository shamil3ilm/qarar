<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventReminder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Reminder methods
    public const METHOD_NOTIFICATION = 'notification';
    public const METHOD_EMAIL = 'email';
    public const METHOD_SMS = 'sms';
}