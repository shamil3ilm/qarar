<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_determination_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('document_type', [
                'sales_invoice',
                'purchase_bill',
                'sales_order',
                'purchase_order',
                'all',
            ]);
            $table->char('from_country_code', 2)->nullable();
            $table->char('to_country_code', 2)->nullable();
            $table->string('from_region', 100)->nullable();
            $table->string('to_region', 100)->nullable();
            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            $table->enum('customer_type', ['b2b', 'b2c', 'government', 'exempt', 'any'])->default('any');
            $table->enum('tax_type', ['standard', 'zero', 'exempt', 'reverse_charge', 'out_of_scope']);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->boolean('is_reverse_charge')->default(false);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type', 'is_active'], 'tax_determination_rules_org_doc_type_active_idx');
            $table->index(['organization_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_determination_rules');
    }
};
