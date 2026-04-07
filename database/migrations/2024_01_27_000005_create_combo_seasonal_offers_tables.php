<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product bundles / combo offers
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 50);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            // Pricing
            $table->string('pricing_type', 20)->default('fixed'); // fixed, percentage_discount, custom
            $table->decimal('bundle_price', 15, 2)->nullable(); // For fixed pricing
            $table->decimal('discount_percent', 5, 2)->nullable(); // For percentage discount
            $table->decimal('original_total', 15, 2)->default(0); // Sum of individual items
            $table->decimal('savings_amount', 15, 2)->default(0); // How much customer saves

            // Availability
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->boolean('is_limited_time')->default(false);

            // Stock
            $table->unsignedInteger('max_quantity')->nullable(); // Max bundles available
            $table->unsignedInteger('sold_quantity')->default(0);

            // Restrictions
            $table->unsignedSmallInteger('min_order_quantity')->default(1);
            $table->unsignedSmallInteger('max_order_quantity')->nullable();
            $table->json('eligible_customer_tiers')->nullable(); // Tier codes allowed

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'sku']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['available_from', 'available_until']);
        });

        // Bundle items (products in the combo)
        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->text('description')->nullable();
            $table->decimal('original_price', 15, 4)->nullable(); // Individual item price
            $table->decimal('unit_price', 15, 4)->nullable(); // Unit price alias
            $table->decimal('bundle_price', 15, 4)->nullable(); // Override price in bundle
            $table->decimal('discount_percentage', 8, 4)->nullable()->default(0); // Discount percentage
            $table->boolean('is_optional')->default(false); // Customer can choose to exclude
            $table->boolean('is_default_selected')->default(true); // Pre-selected for optional items
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['bundle_id', 'display_order']);
        });

        // Seasonal / time-limited campaigns
        Schema::create('seasonal_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('campaign_type', 30); // seasonal, flash_sale, clearance, holiday, back_to_school, ramadan, diwali, eid, national_day
            $table->string('banner_image')->nullable();
            $table->string('theme_color', 7)->nullable();

            // Dates
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_recurring')->default(false); // Same campaign every year
            $table->string('recurrence_rule')->nullable(); // yearly, quarterly

            // Discount settings
            $table->string('discount_type', 20)->nullable(); // percentage, fixed_amount, tiered
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('min_purchase', 15, 2)->nullable();

            // Scope
            $table->string('applies_to', 30)->default('all'); // all, categories, products, bundles
            $table->json('applicable_category_ids')->nullable();
            $table->json('applicable_product_ids')->nullable();
            $table->json('applicable_bundle_ids')->nullable();
            $table->json('excluded_product_ids')->nullable();

            // Limits
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->decimal('budget_limit', 15, 2)->nullable(); // Max total discount given
            $table->decimal('budget_used', 15, 2)->default(0);

            // Messaging
            $table->text('promotional_message')->nullable();
            $table->boolean('send_notification')->default(false);
            $table->boolean('show_countdown')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0); // Higher = checked first
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active', 'starts_at', 'ends_at'], 'season_camp_org_active_dates_idx');
            $table->index(['campaign_type']);
        });

        // Campaign tier-specific offers
        Schema::create('campaign_tier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('seasonal_campaigns')->cascadeOnDelete();
            $table->string('tier_code', 30)->nullable(); // Links to customer_tiers.code
            $table->string('tier_name', 100)->nullable();
            $table->decimal('min_purchase_amount', 15, 2)->nullable();
            $table->string('discount_type', 20)->nullable(); // percentage, fixed_amount
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('extra_discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('bonus_points')->default(0);
            $table->boolean('early_access')->default(false);
            $table->unsignedSmallInteger('early_access_hours')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'tier_code']);
        });

        // Product tags for differentiation
        Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 50);
            $table->string('color', 7)->nullable();
            $table->string('tag_group', 30)->nullable(); // season, material, brand, origin, diet, etc.
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'tag_group']);
        });

        // Product to tag pivot
        Schema::create('product_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('product_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'tag_id']);
        });

        // Product attributes for differentiation
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('type', 20); // text, number, select, multi_select, boolean, color
            $table->json('options')->nullable(); // For select/multi_select types
            $table->string('unit')->nullable(); // kg, cm, ml
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_comparable')->default(false); // Show in product comparison
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        // Product attribute values
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 15, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable(); // For multi_select
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id']);
        });

        // TEXT column needs prefix length for indexing in MySQL; SQLite doesn't need it
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX attr_val_attr_id_value_text_idx ON product_attribute_values (attribute_id, value_text(191))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_tag_assignments');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('campaign_tier_offers');
        Schema::dropIfExists('seasonal_campaigns');
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
    }
};
