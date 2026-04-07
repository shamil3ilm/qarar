<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_document_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->enum('account_type', ['asset', 'liability', 'revenue', 'expense', 'equity', 'all'])->default('all');
            $table->string('number_range_code', 20)->nullable();
            $table->boolean('reverse_document_type')->default(false);
            $table->string('reverse_document_type_code', 10)->nullable();
            $table->boolean('require_reference')->default(false);
            $table->boolean('check_duplicate_invoice')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_document_types');
    }
};
