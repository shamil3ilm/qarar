<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atp_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('source_document_id');
            $table->string('source_document_type', 30);
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('requested_quantity', 15, 4);
            $table->decimal('confirmed_quantity', 15, 4)->default(0);
            $table->date('requested_date');
            $table->date('confirmed_date')->nullable();
            $table->json('availability_breakdown')->nullable();
            $table->enum('result', ['full', 'partial', 'none'])->default('none');
            $table->timestamps();
            $table->index(['source_document_type', 'source_document_id'], 'atp_doc_idx');
            $table->index(['organization_id', 'product_id'], 'atp_org_product_idx');
        });

        Schema::create('customer_material_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('customer_material_number', 100)->nullable();
            $table->string('customer_material_description', 255)->nullable();
            $table->integer('delivery_lead_time_days')->default(0);
            $table->decimal('minimum_order_quantity', 15, 4)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'contact_id', 'product_id'], 'cmi_org_contact_product_unique');
            $table->index(['contact_id', 'customer_material_number'], 'cmi_contact_mat_num_idx');
        });

        Schema::create('output_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->enum('document_type', ['invoice', 'sales_order', 'quotation', 'delivery_note', 'purchase_order', 'payment'])->default('invoice');
            $table->enum('output_medium', ['print', 'email', 'edi', 'portal'])->default('email');
            $table->string('email_template', 100)->nullable();
            $table->string('print_template', 100)->nullable();
            $table->enum('dispatch_time', ['immediately', 'on_save', 'on_post', 'scheduled'])->default('on_post');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code', 'document_type'], 'ot_org_code_doc_unique');
        });

        Schema::create('output_condition_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_type_id')->constrained('output_types')->cascadeOnDelete();
            $table->enum('key_combination', ['customer', 'customer_group', 'all'])->default('all');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['output_type_id', 'key_combination'], 'ocr_type_key_idx');
        });

        Schema::create('output_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_type_id')->constrained('output_types')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->enum('medium', ['print', 'email', 'edi', 'portal'])->default('email');
            $table->string('recipient', 255)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
            $table->index(['document_type', 'document_id'], 'om_doc_idx');
            $table->index(['status', 'scheduled_at'], 'om_status_sched_idx');
        });

        Schema::create('delivery_split_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_name', 100);
            $table->enum('split_criteria', ['warehouse', 'delivery_date', 'route', 'weight', 'volume'])->default('warehouse');
            $table->enum('applies_to', ['all_customers', 'customer_group', 'specific_customer'])->default('all_customers');
            $table->unsignedBigInteger('applies_to_id')->nullable();
            $table->boolean('allow_partial_delivery')->default(true);
            $table->decimal('minimum_delivery_quantity_pct', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'delivery_split_rules_org_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_split_rules');
        Schema::dropIfExists('output_messages');
        Schema::dropIfExists('output_condition_records');
        Schema::dropIfExists('output_types');
        Schema::dropIfExists('customer_material_infos');
        Schema::dropIfExists('atp_checks');
    }
};
