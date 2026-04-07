<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('permit_safety_checks');
        Schema::dropIfExists('maintenance_permits');

        Schema::create('maintenance_permits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('maintenance_order_id')->nullable();
            $table->foreign('maintenance_order_id', 'mp_mo_fk')->references('id')->on('maintenance_orders');
            $table->string('permit_number', 50);
            $table->enum('permit_type', ['hot_work', 'confined_space', 'electrical_isolation', 'height_work', 'chemical', 'general'])->default('general');
            $table->enum('status', ['requested', 'approved', 'active', 'suspended', 'closed', 'cancelled'])->default('requested');
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('work_description')->nullable();
            $table->text('hazards_identified')->nullable();
            $table->text('precautions_required')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->foreign('requested_by', 'mp_requested_by_fk')->references('id')->on('users');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'mp_approved_by_fk')->references('id')->on('users');
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->foreign('closed_by', 'mp_closed_by_fk')->references('id')->on('users');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'permit_number'], 'mp_org_number_unq');
            $table->index(['organization_id', 'status'], 'mp_org_status_idx');
            $table->index(['maintenance_order_id'], 'mp_mo_idx');
        });

        Schema::create('permit_safety_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'psc_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('maintenance_permit_id');
            $table->foreign('maintenance_permit_id', 'psc_permit_fk')->references('id')->on('maintenance_permits');
            $table->string('check_description', 255);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->foreign('completed_by', 'psc_completed_by_fk')->references('id')->on('users');
            $table->dateTime('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['maintenance_permit_id'], 'psc_permit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_safety_checks');
        Schema::dropIfExists('maintenance_permits');
    }
};
