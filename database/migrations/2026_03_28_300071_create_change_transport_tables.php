<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_transport_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ctr_org_id_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->string('request_number', 20);
            $table->string('description', 200);
            $table->string('request_type', 20)
                ->comment('workbench/customizing/transport_of_copies');
            $table->string('category', 30)
                ->comment('feature/bugfix/configuration/data_migration');
            $table->string('target_environment', 20)
                ->comment('quality/production/staging');
            $table->string('status', 20)->default('open')
                ->comment('open/released/imported/failed');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by', 'ctr_created_by_fk')
                ->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('released_by')->nullable();
            $table->foreign('released_by', 'ctr_released_by_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->dateTime('released_at')->nullable();
            $table->dateTime('imported_at')->nullable();
            $table->text('import_log')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('request_number', 'ctr_request_number_idx');
            $table->index(['status', 'target_environment'], 'ctr_status_env_idx');
            $table->index(['created_by', 'status'], 'ctr_created_status_idx');
        });

        Schema::create('change_transport_objects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('change_transport_request_id');
            $table->foreign('change_transport_request_id', 'cto_request_id_fk')
                ->references('id')->on('change_transport_requests')->onDelete('cascade');
            $table->string('object_type', 50)
                ->comment('migration/config/route/permission/setting');
            $table->string('object_name', 200);
            $table->string('object_key', 200)->nullable();
            $table->string('change_type', 20)
                ->comment('create/modify/delete');
            $table->json('payload')->nullable();
            $table->string('checksums', 64)->nullable();
            $table->timestamps();

            $table->index('change_transport_request_id', 'cto_req_id_idx');
        });

        Schema::create('change_transport_object_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_transport_request_id');
            $table->foreign('change_transport_request_id', 'ctoa_request_id_fk')
                ->references('id')->on('change_transport_requests')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'ctoa_user_id_fk')
                ->references('id')->on('users')->onDelete('cascade');
            $table->dateTime('assigned_at');
            $table->timestamps();
        });

        Schema::create('change_transport_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_transport_request_id');
            $table->foreign('change_transport_request_id', 'ctl_request_id_fk')
                ->references('id')->on('change_transport_requests')->onDelete('cascade');
            $table->string('action', 50)
                ->comment('created/object_added/released/import_started/imported/failed/rollback');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->foreign('performed_by', 'ctl_performed_by_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->string('environment', 20)->nullable();
            $table->text('message')->nullable();
            $table->dateTime('created_at');

            $table->index('change_transport_request_id', 'ctl_req_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_transport_logs');
        Schema::dropIfExists('change_transport_object_assignments');
        Schema::dropIfExists('change_transport_objects');
        Schema::dropIfExists('change_transport_requests');
    }
};
