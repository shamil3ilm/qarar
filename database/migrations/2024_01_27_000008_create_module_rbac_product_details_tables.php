<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // MODULE-LEVEL ROLE-BASED ACCESS CONTROL
        // =====================================================================

        // Module definitions (what modules exist in the system)
        Schema::create('module_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique(); // sales, purchase, inventory, accounting, hr, crm, manufacturing, etc.
            $table->string('group', 50); // core, finance, operations, hr, marketing, advanced
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('sub_modules')->nullable(); // ['invoices', 'quotations', 'payments']
            $table->json('required_modules')->nullable(); // Dependencies: inventory requires accounting
            $table->string('min_subscription_tier', 20)->default('free'); // free, starter, professional, enterprise
            $table->boolean('is_core')->default(false); // Core modules can't be disabled
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        // Which modules are enabled per organization
        Schema::create('organization_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->date('enabled_at')->nullable();
            $table->date('disabled_at')->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('config')->nullable(); // Module-specific config overrides
            $table->timestamps();

            $table->unique(['organization_id', 'module_id']);
        });

        // Role-module permissions (which role can access which module and what actions)
        Schema::create('role_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();

            // CRUD + special permissions
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_import')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_print')->default(false);

            // Data scope
            $table->string('data_scope', 30)->default('own'); // own, branch, department, organization, all
            // own = only records they created
            // branch = records in their branch
            // department = records in their department
            // organization = all records in org
            // all = no restrictions (super admin)

            // Financial limits
            $table->decimal('max_amount_limit', 15, 2)->nullable(); // Max transaction amount
            $table->decimal('max_discount_percent', 5, 2)->nullable(); // Max discount they can give

            // Custom permissions for this module
            $table->json('custom_permissions')->nullable(); // Module-specific extras

            $table->timestamps();

            $table->unique(['role_id', 'module_id']);
            $table->index(['role_id']);
            $table->index(['module_id']);
        });

        // User-specific module overrides (override role defaults per user)
        Schema::create('user_module_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();

            // Override type
            $table->string('override_type', 20); // grant, revoke, restrict

            // Same fields as role_module_permissions (NULL = inherit from role)
            $table->boolean('can_view')->nullable();
            $table->boolean('can_create')->nullable();
            $table->boolean('can_edit')->nullable();
            $table->boolean('can_delete')->nullable();
            $table->boolean('can_export')->nullable();
            $table->boolean('can_import')->nullable();
            $table->boolean('can_approve')->nullable();
            $table->string('data_scope', 30)->nullable();
            $table->decimal('max_amount_limit', 15, 2)->nullable();
            $table->json('custom_permissions')->nullable();

            $table->text('reason')->nullable(); // Why this override was set
            $table->date('expires_at')->nullable(); // Temporary access
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
        });

        // Menu/navigation configuration per role
        Schema::create('role_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();
            $table->string('menu_label');
            $table->string('menu_icon')->nullable();
            $table->string('route_name')->nullable();
            $table->string('parent_menu')->nullable(); // For nested menus
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_pinned')->default(false); // Pinned to sidebar/favorites
            $table->timestamps();

            $table->index(['role_id', 'position']);
        });

        // Access log (who accessed which module when)
        Schema::create('module_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();
            $table->string('action', 50); // view, create, edit, delete, approve, export, import
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->boolean('was_allowed')->default(true);
            $table->string('denial_reason')->nullable(); // Why access was denied
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at');

            $table->index(['organization_id', 'accessed_at']);
            $table->index(['user_id', 'accessed_at']);
            $table->index(['module_id', 'was_allowed']);
        });

        // =====================================================================
        // RICH PRODUCT DETAILS
        // =====================================================================

        // Product specifications (key-value technical specs)
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('spec_group')->nullable(); // "Physical", "Technical", "Packaging"
            $table->string('spec_name'); // "Weight", "Dimensions", "Color"
            $table->text('spec_value'); // "500g", "10x20x5 cm"
            $table->string('unit')->nullable(); // "kg", "cm", "ml"
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'spec_group']);
        });

        // Product images (multiple images per product)
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('image_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('image_type', 20)->default('gallery'); // gallery, thumbnail, cover, swatch, zoom, lifestyle
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_primary']);
            $table->index(['variant_id']);
        });

        // Product documents (manuals, datasheets, certifications)
        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size');
            $table->string('document_type', 30); // manual, datasheet, certificate, warranty, safety, brochure
            $table->string('language', 5)->default('en');
            $table->boolean('is_public')->default(true); // Visible to customers
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'document_type']);
        });

        // Product videos
        Schema::create('product_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title');
            $table->string('video_type', 20); // uploaded, youtube, vimeo, external
            $table->string('video_url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id']);
        });

        // Product related/cross-sell/up-sell
        Schema::create('product_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('relation_type', 20); // related, cross_sell, up_sell, accessory, spare_part, substitute, frequently_bought
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id', 'relation_type'], 'prod_rels_prod_related_type_unique');
        });

        // Product reviews and ratings (if customer-facing)
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('reviewer_name');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('review_text')->nullable();
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, flagged
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status', 'rating']);
            $table->index(['organization_id', 'status']);
        });

        // Product pricing history (track all price changes)
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('price_type', 30); // selling_price, purchase_price, mrp, wholesale, special
            $table->decimal('old_price', 15, 4);
            $table->decimal('new_price', 15, 4);
            $table->decimal('change_percent', 8, 2);
            $table->string('currency_code', 3);
            $table->string('reason')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'price_type', 'effective_from']);
        });

        // Product compliance/certifications (Halal, ISO, organic, etc.)
        Schema::create('product_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('certification_name'); // Halal, ISO 9001, Organic, CE, FDA
            $table->string('certification_body')->nullable(); // Issuing authority
            $table->string('certificate_number')->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('certificate_file_path')->nullable();
            $table->string('status', 20)->default('active'); // active, expired, pending, revoked
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['expiry_date']);
        });

        // Enrich products table with detail columns (skip already existing columns)
        $newColumns = [
            'long_description' => fn ($t) => $t->text('long_description')->nullable()->after('description'),
            'short_description' => fn ($t) => $t->text('short_description')->nullable()->after('description'),
            'brand' => fn ($t) => $t->string('brand')->nullable()->after('name'),
            'manufacturer' => fn ($t) => $t->string('manufacturer')->nullable()->after('brand'),
            'model_number' => fn ($t) => $t->string('model_number')->nullable()->after('manufacturer'),
            'country_of_origin' => fn ($t) => $t->string('country_of_origin', 3)->nullable()->after('model_number'),
            'mrp' => fn ($t) => $t->decimal('mrp', 15, 4)->nullable()->after('selling_price'),
            'wholesale_price' => fn ($t) => $t->decimal('wholesale_price', 15, 4)->nullable()->after('selling_price'),
            'minimum_order_qty' => fn ($t) => $t->decimal('minimum_order_qty', 15, 4)->nullable(),
            'maximum_order_qty' => fn ($t) => $t->decimal('maximum_order_qty', 15, 4)->nullable(),
            'warranty_type' => fn ($t) => $t->string('warranty_type', 30)->nullable(),
            'warranty_months' => fn ($t) => $t->unsignedSmallInteger('warranty_months')->nullable(),
            'warranty_terms' => fn ($t) => $t->text('warranty_terms')->nullable(),
            'shelf_life_days' => fn ($t) => $t->string('shelf_life_days')->nullable(),
            'seo_meta' => fn ($t) => $t->json('seo_meta')->nullable(),
            'is_featured' => fn ($t) => $t->boolean('is_featured')->default(false),
            'is_new_arrival' => fn ($t) => $t->boolean('is_new_arrival')->default(false),
            'is_bestseller' => fn ($t) => $t->boolean('is_bestseller')->default(false),
            'is_returnable' => fn ($t) => $t->boolean('is_returnable')->default(true),
            'is_taxable' => fn ($t) => $t->boolean('is_taxable')->default(true),
            'requires_shipping' => fn ($t) => $t->boolean('requires_shipping')->default(true),
        ];

        Schema::table('products', function (Blueprint $table) use ($newColumns) {
            foreach ($newColumns as $column => $definition) {
                if (!Schema::hasColumn('products', $column)) {
                    $definition($table);
                }
            }
        });
    }

    public function down(): void
    {
        $columnsToDrop = array_filter([
            'long_description', 'short_description', 'brand', 'manufacturer',
            'model_number', 'country_of_origin', 'mrp',
            'wholesale_price', 'minimum_order_qty', 'maximum_order_qty',
            'warranty_type', 'warranty_months', 'warranty_terms',
            'shelf_life_days', 'seo_meta', 'is_featured', 'is_new_arrival',
            'is_bestseller', 'is_returnable', 'is_taxable',
            'requires_shipping',
        ], fn ($col) => Schema::hasColumn('products', $col));

        if (!empty($columnsToDrop)) {
            Schema::table('products', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        Schema::dropIfExists('product_certifications');
        Schema::dropIfExists('product_price_history');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_videos');
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_specifications');
        Schema::dropIfExists('module_access_logs');
        Schema::dropIfExists('role_menu_items');
        Schema::dropIfExists('user_module_overrides');
        Schema::dropIfExists('role_module_permissions');
        Schema::dropIfExists('organization_module_access');
        Schema::dropIfExists('module_definitions');
    }
};
