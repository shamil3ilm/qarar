<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_limit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('previous_limit', 15, 2)->default(0);
            $table->decimal('new_limit', 15, 2)->default(0);
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'contact_id'], 'credit_hist_org_contact_idx');
            $table->index('contact_id', 'credit_hist_contact_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_limit_histories');
    }
};
