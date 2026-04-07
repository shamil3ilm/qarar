<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['quality', 'delivery', 'price', 'service', 'compliance'])->default('quality');
            $table->decimal('weight_percent', 5, 2)->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('supplier_scorecards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('evaluation_period_start');
            $table->date('evaluation_period_end');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->decimal('delivery_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->decimal('service_score', 5, 2)->nullable();
            $table->decimal('compliance_score', 5, 2)->nullable();
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'evaluation_period_start', 'evaluation_period_end'], 'unique_supplier_scorecard_period');
            $table->index('organization_id');
        });

        Schema::create('supplier_scorecard_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('supplier_scorecards')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('supplier_evaluation_criteria')->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_delivery_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('promised_date');
            $table->date('actual_date')->nullable();
            $table->decimal('quantity_ordered', 12, 4);
            $table->decimal('quantity_received', 12, 4)->default(0);
            $table->boolean('is_on_time')->nullable();
            $table->boolean('is_complete')->nullable();
            $table->boolean('quality_accepted')->nullable();
            $table->decimal('defect_quantity', 12, 4)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index(['organization_id', 'supplier_id']);
        });

        Schema::create('supplier_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->enum('incident_type', ['late_delivery', 'quality_issue', 'pricing_dispute', 'compliance_breach', 'communication'])->default('quality_issue');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->date('occurred_at');
            $table->date('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_incidents');
        Schema::dropIfExists('supplier_delivery_records');
        Schema::dropIfExists('supplier_scorecard_ratings');
        Schema::dropIfExists('supplier_scorecards');
        Schema::dropIfExists('supplier_evaluation_criteria');
    }
};
