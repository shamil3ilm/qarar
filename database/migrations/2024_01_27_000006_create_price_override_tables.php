<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Price override policies (who can change prices and by how much)
        Schema::create('price_override_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // What can be overridden
            $table->boolean('allow_price_change')->default(true);
            $table->boolean('allow_discount')->default(true);
            $table->boolean('allow_markup')->default(false); // Above list price
            $table->boolean('allow_free_item')->default(false); // Price = 0

            // Limits
            $table->decimal('max_discount_percent', 5, 2)->nullable(); // Max % below list price
            $table->decimal('max_markup_percent', 5, 2)->nullable(); // Max % above list price
            $table->decimal('max_discount_amount', 15, 2)->nullable(); // Max flat discount per item
            $table->decimal('min_price_percent', 5, 2)->nullable(); // Floor price as % of cost
            $table->decimal('max_total_discount_percent', 5, 2)->nullable(); // Max % off entire order

            // Approval requirements
            $table->boolean('requires_approval')->default(false);
            $table->decimal('approval_threshold_percent', 5, 2)->nullable(); // Needs approval above this %
            $table->decimal('approval_threshold_amount', 15, 2)->nullable(); // Needs approval above this amount
            $table->boolean('requires_reason')->default(true);

            // Scope
            $table->string('applies_to', 30)->default('all'); // all, roles, users, branches
            $table->json('applicable_role_ids')->nullable();
            $table->json('applicable_user_ids')->nullable();
            $table->json('applicable_branch_ids')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        // Price override log (audit trail for every price change at billing)
        Schema::create('price_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Source document
            $table->string('document_type', 100)->nullable(); // Invoice, Quotation, SalesOrder, Bill, PurchaseOrder
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('line_item_id')->nullable(); // The specific line item

            // Product
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Prices
            $table->decimal('original_price', 15, 4)->default(0); // List/catalog price
            $table->decimal('override_price', 15, 4)->default(0); // New price set at billing
            $table->decimal('cost_price', 15, 4)->nullable(); // Product cost (for margin check)
            $table->decimal('price_difference', 15, 4)->default(0); // override - original
            $table->decimal('discount_percent', 5, 2)->default(0); // % change
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('total_impact', 15, 2)->default(0); // Total monetary impact

            // Override type
            $table->string('override_type', 30)->nullable(); // discount, markup, custom_price, price_match, negotiated, manager_override
            $table->string('reason_code', 30)->nullable(); // competitor_match, bulk_order, loyalty, damaged, negotiated, clearance
            $table->text('reason')->nullable(); // Free-text reason
            $table->text('notes')->nullable();

            // Approval
            $table->string('approval_status', 20)->default('auto_approved'); // auto_approved, pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Reference
            $table->foreignId('policy_id')->nullable()->constrained('price_override_policies')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Margin impact
            $table->decimal('margin_before', 5, 2)->nullable(); // Margin % at original price
            $table->decimal('margin_after', 5, 2)->nullable(); // Margin % at override price

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['document_type', 'document_id']);
            $table->index(['product_id']);
            $table->index(['created_by', 'created_at']);
            $table->index(['approval_status']);
            $table->index(['override_type']);
        });

        // Price override reason codes (configurable per org)
        Schema::create('price_override_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('requires_evidence')->default(false); // Attach competitor price screenshot, etc.
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        // Add price override columns to invoice_lines, quotation_lines, sales_order_lines
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('original_price', 15, 4)->nullable()->after('unit_price');
            $table->boolean('price_overridden')->default(false)->after('original_price');
            $table->string('override_reason', 100)->nullable()->after('price_overridden');
            $table->foreignId('override_approved_by')->nullable()->after('override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'price_overridden', 'override_reason', 'override_approved_by']);
        });

        Schema::dropIfExists('price_override_reasons');
        Schema::dropIfExists('price_overrides');
        Schema::dropIfExists('price_override_policies');
    }
};
