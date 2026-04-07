<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->boolean('payment_block')->default(false)->after('is_active');
            $table->string('payment_block_reason', 500)->nullable()->after('payment_block');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['payment_block', 'payment_block_reason']);
        });
    }
};
