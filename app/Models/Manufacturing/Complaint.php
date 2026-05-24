<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'complaint_number', 'complaint_source',
        'contact_id', 'subject', 'description', 'priority', 'status',
        'assigned_to_id', 'received_date', 'target_resolution_date', 'actual_resolution_date',
    ];

    protected $casts = [
        'received_date'           => 'date',
        'target_resolution_date'  => 'date',
        'actual_resolution_date'  => 'date',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(ComplaintCommunication::class);
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(ComplaintResolution::class);
    }
}
