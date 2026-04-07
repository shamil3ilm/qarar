<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->boolean('is_auc')->default(false)->after('status')
                ->comment('Asset Under Construction flag (SAP AuC)');
            $table->decimal('auc_settled_amount', 15, 4)->default(0)->after('is_auc')
                ->comment('Cumulative amount already settled to final assets');
            $table->timestamp('auc_settled_at')->nullable()->after('auc_settled_amount')
                ->comment('Timestamp of final full settlement');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropColumn(['is_auc', 'auc_settled_amount', 'auc_settled_at']);
        });
    }
};
