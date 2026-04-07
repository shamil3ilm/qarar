<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpq_configurable_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 18, 4)->default(0);
            $table->string('currency_code', 3);
            $table->integer('configuration_validity_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'cpq_prod_org_active_idx');
        });

        Schema::create('cpq_option_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->string('group_code', 30);
            $table->string('name');
            $table->string('selection_type', 20)->default('single'); // single|multi
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cpq_options', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_option_group_id')
                ->constrained('cpq_option_groups')
                ->cascadeOnDelete();
            $table->string('option_code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('price_modifier_type', 20)->default('none'); // fixed|percentage|none
            $table->decimal('price_modifier_value', 18, 4)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('linked_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cpq_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->string('rule_name');
            $table->json('condition_json');
            $table->string('discount_type', 20); // percentage|fixed|price_override
            $table->decimal('discount_value', 18, 4);
            $table->integer('priority')->default(50);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cpq_constraint_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->string('rule_type', 20); // requires|excludes|includes
            $table->foreignId('if_option_id')
                ->nullable()
                ->constrained('cpq_options')
                ->nullOnDelete();
            $table->foreignId('then_option_id')
                ->nullable()
                ->constrained('cpq_options')
                ->nullOnDelete();
            $table->string('error_message', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cpq_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('configuration_code', 30);
            $table->string('status', 20)->default('draft'); // draft|valid|expired|converted
            $table->decimal('total_price', 18, 4);
            $table->string('currency_code', 3);
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contact_id', 'status'], 'cpq_cfg_contact_status_idx');
            $table->index(['status', 'valid_until'], 'cpq_cfg_status_valid_idx');
        });

        Schema::create('cpq_configuration_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configuration_id')
                ->constrained('cpq_configurations')
                ->cascadeOnDelete();
            $table->foreignId('cpq_option_group_id')
                ->constrained('cpq_option_groups')
                ->cascadeOnDelete();
            $table->foreignId('cpq_option_id')
                ->constrained('cpq_options')
                ->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_total', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpq_configuration_items');
        Schema::dropIfExists('cpq_configurations');
        Schema::dropIfExists('cpq_constraint_rules');
        Schema::dropIfExists('cpq_pricing_rules');
        Schema::dropIfExists('cpq_options');
        Schema::dropIfExists('cpq_option_groups');
        Schema::dropIfExists('cpq_configurable_products');
    }
};
