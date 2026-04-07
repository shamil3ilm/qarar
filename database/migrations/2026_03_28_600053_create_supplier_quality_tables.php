<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('supplier_ncr_records');
        Schema::dropIfExists('approved_vendor_lists');
        Schema::dropIfExists('supplier_quality_ratings');

        Schema::create('supplier_quality_ratings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id', 'sq_rating_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete();
            $table->date('rating_period_start');
            $table->date('rating_period_end');
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->decimal('delivery_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->enum('classification', ['preferred', 'approved', 'conditional', 'disqualified'])->default('approved');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('evaluated_by_id')->nullable();
            $table->foreign('evaluated_by_id', 'sq_rating_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('approved_vendor_lists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id', 'avl_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'avl_product_fk')->references('id')->on('products')->nullOnDelete();
            $table->date('approved_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['active', 'suspended', 'expired', 'revoked'])->default('active');
            $table->text('approval_conditions')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_ncr_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('ncr_number', 50)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id', 'sq_ncr_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'sq_ncr_product_fk')->references('id')->on('products')->nullOnDelete();
            $table->string('po_number', 50)->nullable();
            $table->text('nonconformance_description');
            $table->enum('severity', ['critical', 'major', 'minor'])->default('minor');
            $table->enum('disposition', ['use_as_is', 'rework', 'repair', 'return_to_supplier', 'scrap'])->nullable();
            $table->enum('status', ['open', 'supplier_response_pending', 'under_review', 'closed'])->default('open');
            $table->date('detected_date');
            $table->date('closed_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ncr_records');
        Schema::dropIfExists('approved_vendor_lists');
        Schema::dropIfExists('supplier_quality_ratings');
    }
};
