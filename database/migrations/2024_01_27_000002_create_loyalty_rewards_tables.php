<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer tiers / loyalty programs
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('currency_name', 50)->default('Points'); // Points, Stars, Miles, etc.
            $table->string('currency_symbol', 10)->default('pts');
            $table->decimal('point_value', 10, 4)->default(0.01); // Monetary value per point
            $table->decimal('earn_rate', 10, 4)->default(1); // Points per currency unit spent
            $table->unsignedInteger('min_redeem_points')->default(100);
            $table->unsignedInteger('points_expiry_days')->nullable(); // NULL = never expire
            $table->boolean('allow_partial_redeem')->default(true);
            $table->boolean('earn_on_tax')->default(false);
            $table->boolean('earn_on_shipping')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        // Customer tiers (Bronze, Silver, Gold, Platinum, etc.)
        Schema::create('customer_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('color', 7)->nullable();
            $table->string('icon')->nullable();

            // Qualification criteria
            $table->string('qualification_type', 30)->default('spending'); // spending, points, manual
            $table->decimal('min_spending', 15, 2)->default(0); // Min spending to qualify
            $table->unsignedInteger('min_points')->default(0); // Min points to qualify
            $table->unsignedSmallInteger('qualification_period_months')->nullable(); // Rolling period

            // Benefits
            $table->decimal('earn_rate_multiplier', 5, 2)->default(1.00); // 1.5x, 2x points
            $table->decimal('discount_percent', 5, 2)->default(0); // Auto discount on purchases
            $table->boolean('free_shipping')->default(false);
            $table->unsignedSmallInteger('priority_support_level')->default(0); // 0 = none
            $table->json('perks')->nullable(); // Additional tier benefits

            // Downgrade/upgrade
            $table->boolean('auto_upgrade')->default(true);
            $table->boolean('auto_downgrade')->default(true);
            $table->unsignedSmallInteger('grace_period_days')->default(30); // Before downgrade

            $table->unsignedSmallInteger('tier_level')->default(0); // 0 = base, 1, 2, 3...
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'tier_level']);
        });

        // Customer loyalty accounts
        Schema::create('customer_loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->foreignId('customer_tier_id')->nullable()->constrained('customer_tiers')->nullOnDelete();
            $table->string('membership_number', 30)->nullable();

            // Points
            $table->unsignedBigInteger('total_earned_points')->default(0);
            $table->unsignedBigInteger('total_redeemed_points')->default(0);
            $table->unsignedBigInteger('total_expired_points')->default(0);
            $table->unsignedBigInteger('available_points')->default(0);
            $table->unsignedBigInteger('pending_points')->default(0); // Earned but not yet available

            // Spending
            $table->decimal('total_spending', 15, 2)->default(0);
            $table->decimal('spending_this_period', 15, 2)->default(0);

            // Dates
            $table->date('enrolled_at');
            $table->date('tier_qualified_at')->nullable();
            $table->date('tier_expires_at')->nullable();
            $table->date('last_activity_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id', 'loyalty_program_id'], 'cust_loyalty_org_contact_program_unique');
            $table->index(['membership_number']);
        });

        // Points transactions
        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loyalty_account_id')->constrained('customer_loyalty_accounts')->cascadeOnDelete();
            $table->string('transaction_type', 30); // earn, redeem, expire, adjust, bonus, refund_reversal
            $table->integer('points'); // Positive for earn, negative for redeem/expire
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('description');

            // Source reference
            $table->string('source_type', 100)->nullable(); // Invoice, Order, manual, etc.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('source_amount', 15, 2)->nullable(); // Order/invoice amount

            // Earn multiplier applied
            $table->decimal('earn_multiplier', 5, 2)->default(1.00);

            // Expiry
            $table->date('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['loyalty_account_id', 'transaction_type']);
            $table->index(['loyalty_account_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['expires_at', 'is_expired']);
        });

        // Rewards catalog (what points can be redeemed for)
        Schema::create('rewards_catalog', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->nullable()->constrained('loyalty_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('reward_type', 30)->nullable(); // discount, product, voucher, cashback, free_shipping, custom
            $table->string('type', 30)->nullable(); // Alias for reward_type
            $table->decimal('value', 15, 2)->nullable(); // Generic value field

            // Cost in points
            $table->unsignedInteger('points_cost')->nullable();
            $table->unsignedInteger('points_required')->nullable(); // Alias for points_cost
            $table->decimal('monetary_value', 15, 2)->nullable(); // Cash equivalent

            // For discount rewards
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->decimal('min_order_amount', 15, 2)->nullable();

            // For product rewards
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Limits
            $table->unsignedInteger('stock_quantity')->nullable(); // NULL = unlimited
            $table->unsignedInteger('redeemed_quantity')->default(0);
            $table->unsignedSmallInteger('max_per_customer')->nullable();
            $table->string('required_tier_code', 30)->nullable(); // Minimum tier required

            // Availability
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['reward_type']);
        });

        // Reward redemptions
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loyalty_account_id')->constrained('customer_loyalty_accounts')->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('rewards_catalog')->cascadeOnDelete();
            $table->foreignId('points_transaction_id')->nullable()->constrained('points_transactions')->nullOnDelete();
            $table->unsignedInteger('points_spent');
            $table->string('status', 20)->default('pending'); // pending, fulfilled, cancelled, expired
            $table->string('redemption_code', 30)->nullable(); // For voucher rewards
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['loyalty_account_id', 'status']);
        });

        // Points earning rules (bonus points for specific actions)
        Schema::create('points_earning_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type', 50); // purchase, registration, birthday, referral, review, category_purchase, product_purchase
            $table->unsignedInteger('bonus_points')->default(0);
            $table->decimal('bonus_multiplier', 5, 2)->default(1.00); // 2x = double points
            $table->json('conditions')->nullable(); // Min amount, specific products/categories
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'trigger_type', 'is_active'], 'pts_rules_org_trigger_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_earning_rules');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('rewards_catalog');
        Schema::dropIfExists('points_transactions');
        Schema::dropIfExists('customer_loyalty_accounts');
        Schema::dropIfExists('customer_tiers');
        Schema::dropIfExists('loyalty_programs');
    }
};
