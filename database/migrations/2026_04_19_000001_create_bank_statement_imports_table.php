<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table was created in 2024_01_24_000001_create_bank_reconciliation_tables.php.
     * This migration only adds the extra indexes introduced later.
     */
    public function up(): void
    {
        $existing = collect(Schema::getIndexes('bank_statement_imports'))
            ->pluck('name');

        Schema::table('bank_statement_imports', function (Blueprint $table) use ($existing): void {
            if (!$existing->contains('bsi_org_status_idx')) {
                $table->index(['organization_id', 'status'], 'bsi_org_status_idx');
            }
            if (!$existing->contains('bsi_account_idx')) {
                $table->index(['bank_account_id'], 'bsi_account_idx');
            }
        });
    }

    public function down(): void
    {
        $existing = collect(Schema::getIndexes('bank_statement_imports'))
            ->pluck('name');

        Schema::table('bank_statement_imports', function (Blueprint $table) use ($existing): void {
            if ($existing->contains('bsi_org_status_idx')) {
                $table->dropIndex('bsi_org_status_idx');
            }
            if ($existing->contains('bsi_account_idx')) {
                $table->dropIndex('bsi_account_idx');
            }
        });
    }
};
