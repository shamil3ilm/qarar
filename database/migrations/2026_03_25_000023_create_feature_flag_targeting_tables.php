<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flag_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('flag_key', 100);
            $table->enum('target_type', ['user', 'branch', 'role', 'percentage']);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'flag_key', 'target_type', 'target_id'],
                'fft_org_flag_type_target_unique'
            );
            $table->index(['organization_id', 'flag_key'], 'fft_org_flag_idx');
        });

        Schema::create('feature_flag_rollout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('flag_key', 100);
            $table->enum('action', [
                'enabled',
                'disabled',
                'target_added',
                'target_removed',
                'rollout_percentage_set',
            ]);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'flag_key'], 'ffrl_org_flag_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_rollout_logs');
        Schema::dropIfExists('feature_flag_targets');
    }
};
