<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('contract_number', 30);
            $table->enum('contract_type', ['sales', 'purchase', 'service', 'maintenance']);
            $table->unsignedBigInteger('contact_id');
            $table->string('title', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('renewal_notice_days')->default(30);
            $table->string('currency_code', 3);
            $table->decimal('total_value', 15, 4)->nullable();
            $table->decimal('billed_amount', 15, 4)->default(0);
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'cancelled'])->default('draft');
            $table->date('signed_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('parent_contract_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('parent_contract_id')->references('id')->on('contracts')->nullOnDelete();

            $table->unique(['organization_id', 'contract_number'], 'contracts_org_number_unique');
            $table->index(['organization_id', 'status'], 'contracts_org_status_idx');
            $table->index(['organization_id', 'end_date'], 'contracts_org_end_date_idx');
        });

        Schema::create('contract_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('line_total', 15, 4)->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->json('delivery_schedule')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete();

            $table->index('contract_id', 'contract_lines_contract_id_idx');
        });

        Schema::create('contract_releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('release_date');
            $table->decimal('amount', 15, 4);
            $table->enum('status', ['pending', 'fulfilled', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');

            $table->index(['contract_id', 'status'], 'contract_releases_contract_status_idx');
        });

        Schema::create('contract_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('milestone_name', 200);
            $table->date('due_date');
            $table->decimal('amount', 15, 4);
            $table->enum('status', ['pending', 'invoiced', 'paid'])->default('pending');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');

            $table->index(['contract_id', 'status'], 'contract_milestones_contract_status_idx');
            $table->index(['contract_id', 'due_date'], 'contract_milestones_contract_due_idx');
        });

        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('document_type', 50);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users');

            $table->index('contract_id', 'contract_documents_contract_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_documents');
        Schema::dropIfExists('contract_milestones');
        Schema::dropIfExists('contract_releases');
        Schema::dropIfExists('contract_lines');
        Schema::dropIfExists('contracts');
    }
};
