<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ecm_affected_objects');
        Schema::dropIfExists('engineering_changes');

        Schema::create('engineering_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('change_number', 50);
            $table->enum('change_type', ['bom_change', 'routing_change', 'product_spec_change', 'drawing_change'])->default('bom_change');
            $table->text('description');
            $table->text('reason')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'implemented', 'cancelled'])->default('draft');
            $table->date('effectivity_date')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->name('ec_requested_by_fk');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->name('ec_approved_by_fk');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('implemented_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'change_number'], 'ec_org_number_unq');
            $table->index(['organization_id', 'status'], 'ec_org_status_idx');
        });

        Schema::create('ecm_affected_objects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('eao_org_fk');
            $table->foreignId('engineering_change_id')->constrained('engineering_changes')->cascadeOnDelete()->name('eao_ec_fk');
            $table->enum('object_type', ['bom', 'routing', 'product', 'drawing'])->default('bom');
            $table->unsignedBigInteger('object_id');
            $table->string('object_reference', 100)->nullable();
            $table->text('change_description')->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->timestamps();

            $table->index(['engineering_change_id'], 'eao_ec_idx');
            $table->index(['object_type', 'object_id'], 'eao_obj_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecm_affected_objects');
        Schema::dropIfExists('engineering_changes');
    }
};
