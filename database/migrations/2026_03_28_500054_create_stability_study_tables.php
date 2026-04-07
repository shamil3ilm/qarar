<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('stability_study_results');
        Schema::dropIfExists('stability_study_time_points');
        Schema::dropIfExists('stability_studies');

        Schema::create('stability_studies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('study_number', 50);
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'ss_product_fk')->references('id')->on('products');
            $table->unsignedBigInteger('inventory_batch_id')->nullable();
            $table->foreign('inventory_batch_id', 'ss_batch_fk')->references('id')->on('inventory_batches');
            $table->enum('study_type', ['real_time', 'accelerated', 'intermediate'])->default('real_time');
            $table->enum('status', ['planned', 'active', 'completed', 'discontinued'])->default('planned');
            $table->date('start_date');
            $table->date('planned_end_date')->nullable();
            $table->string('storage_condition', 100)->nullable();
            $table->string('protocol_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'study_number'], 'ss_org_number_unq');
        });

        Schema::create('stability_study_time_points', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'sstp_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('stability_study_id');
            $table->foreign('stability_study_id', 'sstp_study_fk')->references('id')->on('stability_studies');
            $table->string('time_point', 20);
            $table->date('scheduled_date');
            $table->date('actual_date')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'missed'])->default('scheduled');
            $table->timestamps();
            $table->index(['stability_study_id'], 'sstp_study_idx');
        });

        Schema::create('stability_study_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ssr_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('stability_study_time_point_id');
            $table->foreign('stability_study_time_point_id', 'ssr_timepoint_fk')->references('id')->on('stability_study_time_points');
            $table->string('parameter_name', 100);
            $table->decimal('specification_min', 18, 4)->nullable();
            $table->decimal('specification_max', 18, 4)->nullable();
            $table->decimal('result_value', 18, 4)->nullable();
            $table->string('result_text', 255)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->boolean('is_pass')->nullable();
            $table->unsignedBigInteger('tested_by')->nullable();
            $table->foreign('tested_by', 'ssr_tested_by_fk')->references('id')->on('users');
            $table->timestamps();
            $table->index(['stability_study_time_point_id'], 'ssr_timepoint_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stability_study_results');
        Schema::dropIfExists('stability_study_time_points');
        Schema::dropIfExists('stability_studies');
    }
};
