<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hashed recovery codes stored as a JSON array, shown plaintext only once on 2FA enable
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');

            // Timestamp of when the user last completed 2FA setup confirmation
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
