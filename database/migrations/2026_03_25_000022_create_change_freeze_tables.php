<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_freeze_periods', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('reason')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->enum('scope', ['all', 'module'])->default('all');
            $table->json('affected_modules')->nullable();
            $table->json('bypass_roles')->nullable();
            $table->string('bypass_permission', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'starts_at', 'ends_at'], 'change_freeze_org_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_freeze_periods');
    }
};
