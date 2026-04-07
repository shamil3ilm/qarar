<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable ledger_id to journal_entry_lines.
 *
 * NULL = leading (local GAAP) ledger.
 * Non-null = parallel ledger entry (IFRS, tax, management, etc.)
 *
 * Fully backward-compatible: existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ledger_id')
                ->nullable()
                ->after('journal_entry_id')
                ->comment('NULL = leading ledger; non-null = parallel ledger (IFRS/tax/mgmt)');

            $table->index(['ledger_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropIndex(['ledger_id']);
            $table->dropColumn('ledger_id');
        });
    }
};
