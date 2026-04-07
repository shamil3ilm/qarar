<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('batch_selection_strategy')->nullable()->after('has_expiry')
                ->comment('Override batch deduction order: fifo, lifo, fefo. Null falls back to costing_method.');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('batch_selection_strategy');
        });
    }
};
