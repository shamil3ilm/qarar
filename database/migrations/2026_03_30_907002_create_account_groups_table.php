<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->enum('account_category', ['balance_sheet', 'profit_loss', 'statistical']);
            $table->string('number_range_from', 20)->nullable();
            $table->string('number_range_to', 20)->nullable();
            $table->boolean('reconciliation_account')->default(false);
            $table->enum('reconciliation_type', ['customer', 'vendor', 'asset', 'none'])->default('none');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_account_groups');
    }
};
