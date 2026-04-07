<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\Sales\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class EncryptExistingData extends Command
{
    protected $signature = 'security:encrypt-existing-data
                            {--dry-run : Preview which records would be processed without saving}';

    protected $description = 'Encrypt plaintext sensitive fields in Contact, Organization, and Employee records';

    private bool $dryRun = false;

    private int $processed = 0;
    private int $skipped = 0;
    private int $failed = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('[DRY RUN] No changes will be saved.');
        }

        $this->processContacts();
        $this->processOrganizations();
        $this->processEmployees();

        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['Processed', $this->processed],
                ['Skipped (already encrypted)', $this->skipped],
                ['Failed', $this->failed],
            ]
        );

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processContacts(): void
    {
        $this->info('Processing Contact records...');

        Contact::withoutGlobalScopes()->chunkById(100, function ($contacts): void {
            foreach ($contacts as $contact) {
                $this->encryptRecord($contact, ['tax_number']);
            }
        });
    }

    private function processOrganizations(): void
    {
        $this->info('Processing Organization records...');

        Organization::chunkById(100, function ($organizations): void {
            foreach ($organizations as $organization) {
                $this->encryptRecord($organization, ['tax_number']);
            }
        });
    }

    private function processEmployees(): void
    {
        $this->info('Processing Employee records...');

        Employee::withoutGlobalScopes()->chunkById(100, function ($employees): void {
            foreach ($employees as $employee) {
                $this->encryptRecord($employee, [
                    'national_id',
                    'passport_number',
                    'bank_account_number',
                    'bank_iban',
                ]);
            }
        });
    }

    /**
     * Re-save a model's sensitive fields through Eloquent to trigger encryption casts.
     * Fields that are already encrypted (decrypt succeeds) are skipped.
     */
    private function encryptRecord(object $model, array $fields): void
    {
        $needsUpdate = false;
        $updatePayload = [];

        foreach ($fields as $field) {
            $raw = $model->getRawOriginal($field);

            if ($raw === null || $raw === '') {
                continue;
            }

            if ($this->isAlreadyEncrypted($raw)) {
                $this->skipped++;
                continue;
            }

            $updatePayload[$field] = $raw;
            $needsUpdate = true;
        }

        if (! $needsUpdate) {
            return;
        }

        try {
            if (! $this->dryRun) {
                foreach ($updatePayload as $field => $value) {
                    $model->{$field} = $value;
                }
                $model->save();
            }

            $this->processed++;
            $modelClass = class_basename($model);

            if ($this->dryRun) {
                $this->line("[DRY RUN] Would encrypt {$modelClass} ID={$model->id}: " . implode(', ', array_keys($updatePayload)));
            }
        } catch (Throwable $e) {
            $this->failed++;
            $modelClass = class_basename($model);
            $this->error("Failed {$modelClass} ID={$model->id}: {$e->getMessage()}");
        }
    }

    /**
     * Determine whether a stored value is already encrypted by Laravel's Crypt facade.
     */
    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decrypt($value);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
