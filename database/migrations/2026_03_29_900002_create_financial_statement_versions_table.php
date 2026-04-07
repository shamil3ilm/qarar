<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_statement_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['balance_sheet', 'income_statement', 'cash_flow']);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'type', 'is_active'], 'financial_statement_versions_org_type_active_idx');
        });

        Schema::create('financial_statement_version_nodes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fsv_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->enum('node_type', ['header', 'account', 'total']);
            $table->string('label', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->tinyInteger('sign')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('fsv_id')->references('id')->on('financial_statement_versions')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('financial_statement_version_nodes')->nullOnDelete();
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->index(['fsv_id', 'parent_id', 'sort_order'], 'fin_stmt_version_nodes_fsv_parent_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_statement_version_nodes');
        Schema::dropIfExists('financial_statement_versions');
    }
};
