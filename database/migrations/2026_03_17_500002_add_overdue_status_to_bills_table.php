<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'overdue' status to the bills.status ENUM column (MySQL only).
 *
 * SQLite has no ENUM type — the column is a plain string there, so no
 * structural change is needed. The guard prevents test-suite failures.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE bills
            MODIFY COLUMN status ENUM(
                'draft', 'pending', 'approved', 'partial', 'paid', 'overdue', 'voided'
            ) NOT NULL DEFAULT 'draft'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("UPDATE bills SET status = 'approved' WHERE status = 'overdue'");

        DB::statement("
            ALTER TABLE bills
            MODIFY COLUMN status ENUM(
                'draft', 'pending', 'approved', 'partial', 'paid', 'voided'
            ) NOT NULL DEFAULT 'draft'
        ");
    }
};
