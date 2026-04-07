<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_segments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions');
            $table->string('color', 7)->default('#6366f1');
            $table->boolean('is_dynamic')->default(true);
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_segment_memberships', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->references('id')->on('user_segments')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['user_id', 'segment_id']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_event')->nullable();
            $table->json('conditions')->nullable();
            $table->foreignId('target_segment_id')->nullable()->references('id')->on('user_segments')->nullOnDelete();
            $table->json('actions');
            $table->string('status')->default('draft');
            $table->string('schedule_type')->default('immediate');
            $table->unsignedInteger('delay_minutes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('max_sends_per_user')->default(1);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('channel', 20)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['campaign_id', 'user_id']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_sends');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('user_segment_memberships');
        Schema::dropIfExists('user_segments');
    }
};
