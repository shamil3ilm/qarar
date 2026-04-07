<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('capa_effectiveness_reviews');
        Schema::dropIfExists('capa_actions');
        Schema::dropIfExists('capa_records');

        Schema::create('capa_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('capa_number', 50)->unique();
            $table->enum('capa_type', ['corrective', 'preventive'])->default('corrective');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('problem_statement');
            $table->text('root_cause')->nullable();
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'pending_verification', 'closed', 'cancelled'])->default('open');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreign('owner_id', 'capa_owner_fk')->references('id')->on('users')->nullOnDelete();
            $table->date('target_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('capa_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('capa_record_id');
            $table->foreign('capa_record_id', 'capa_action_record_fk')->references('id')->on('capa_records')->cascadeOnDelete();
            $table->string('action_number', 20);
            $table->text('description');
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->foreign('assigned_to_id', 'capa_action_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->date('due_date');
            $table->date('completed_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->text('completion_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('capa_effectiveness_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('capa_record_id');
            $table->foreign('capa_record_id', 'capa_eff_record_fk')->references('id')->on('capa_records')->cascadeOnDelete();
            $table->date('review_date');
            $table->unsignedBigInteger('reviewed_by_id');
            $table->foreign('reviewed_by_id', 'capa_eff_reviewer_fk')->references('id')->on('users');
            $table->enum('effectiveness', ['effective', 'partially_effective', 'not_effective'])->default('effective');
            $table->text('evidence')->nullable();
            $table->text('conclusions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capa_effectiveness_reviews');
        Schema::dropIfExists('capa_actions');
        Schema::dropIfExists('capa_records');
    }
};
