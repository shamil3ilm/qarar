<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCertification extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'training_certifications';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'course_id',
        'enrollment_id',
        'certificate_number',
        'issued_date',
        'expiry_date',
        'is_active',
        'issued_by',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expiry_date' => 'date',
            'is_active'   => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class, 'enrollment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return Carbon::parse($this->expiry_date)->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        $expiry = Carbon::parse($this->expiry_date);

        return !$expiry->isPast() && $expiry->diffInDays(now()) <= $days;
    }

    public function getDaysUntilExpiry(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        $expiry = Carbon::parse($this->expiry_date);

        if ($expiry->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($expiry);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeExpiring(\Illuminate\Database\Eloquent\Builder $query, int $daysAhead = 30): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '>', now()->toDateString())
            ->where('expiry_date', '<=', now()->addDays($daysAhead)->toDateString())
            ->where('is_active', true);
    }
}
