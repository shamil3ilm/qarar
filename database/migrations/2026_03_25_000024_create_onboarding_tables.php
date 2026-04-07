<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('module', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'module', 'is_active'], 'ont_org_module_active_idx');
        });

        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('onboarding_templates')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('step_type', ['action', 'info', 'video', 'link'])->default('action');
            $table->string('action_key', 100)->nullable();
            $table->string('help_url', 500)->nullable();
            $table->boolean('is_required')->default(true);
            $table->tinyInteger('order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['template_id', 'order'], 'ons_template_order_idx');
        });

        Schema::create('user_onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('onboarding_templates')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('onboarding_steps')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'template_id', 'step_id'], 'uop_user_template_step_unique');
            $table->index(['organization_id', 'user_id', 'template_id'], 'uop_org_user_template_idx');
        });

        Schema::create('feature_adoption_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->timestamp('first_used_at');
            $table->timestamp('last_used_at');
            $table->unsignedInteger('usage_count')->default(1);

            $table->unique(['organization_id', 'user_id', 'feature_key'], 'fae_org_user_feature_unique');
            $table->index(['organization_id', 'feature_key'], 'fae_org_feature_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_adoption_events');
        Schema::dropIfExists('user_onboarding_progress');
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('onboarding_templates');
    }
};
