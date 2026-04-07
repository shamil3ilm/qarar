<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class BankStatementImport extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const FILE_TYPE_CSV = 'csv';
    public const FILE_TYPE_OFX = 'ofx';
    public const FILE_TYPE_QFX = 'qfx';
    public const FILE_TYPE_MT940 = 'mt940';

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'user_id',
        'file_name',
        'file_path',
        'file_type',
        'statement_start_date',
        'statement_end_date',
        'total_transactions',
        'imported_transactions',
        'duplicate_transactions',
        'status',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'statement_start_date' => 'date',
            'statement_end_date' => 'date',
            'total_transactions' => 'integer',
            'imported_transactions' => 'integer',
            'duplicate_transactions' => 'integer',
            'errors' => 'array',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markCompleted(int $imported, int $duplicates): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'imported_transactions' => $imported,
            'duplicate_transactions' => $duplicates,
        ]);
    }

    public function markFailed(array $errors): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'errors' => $errors,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForAccount($query, int $bankAccountId)
    {
        return $query->where('bank_account_id', $bankAccountId);
    }
}
