<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rebate_accruals', function (Blueprint $table) {
            $table->string('settlement_ref', 100)->nullable()->after('status');
            $table->timestamp('settled_at')->nullable()->after('settlement_ref');
        });
    }

    public function down(): void
    {
        Schema::table('rebate_accruals', function (Blueprint $table) {
            $table->dropColumn(['settlement_ref', 'settled_at']);
        });
    }
};
