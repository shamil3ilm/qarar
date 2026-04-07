<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('wbs_element_id')->nullable()->after('warehouse_id')
                ->comment('WBS element for project account assignment');
            $table->unsignedBigInteger('project_id')->nullable()->after('wbs_element_id');
            $table->string('account_assignment_type', 20)->nullable()->after('project_id')
                ->comment('K=cost_center, P=project/wbs, F=order, blank=stock');

            if (Schema::hasTable('wbs_elements')) {
                $table->foreign('wbs_element_id', 'pol_wbs_element_fk')
                    ->references('id')->on('wbs_elements')->nullOnDelete();
            }

            if (Schema::hasTable('projects')) {
                $table->foreign('project_id', 'pol_project_fk')
                    ->references('id')->on('projects')->nullOnDelete();
            }

            $table->index('wbs_element_id', 'pol_wbs_element_idx');
            $table->index('project_id', 'pol_project_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            if (Schema::hasTable('wbs_elements')) {
                $table->dropForeign('pol_wbs_element_fk');
            }

            if (Schema::hasTable('projects')) {
                $table->dropForeign('pol_project_fk');
            }

            $table->dropIndex('pol_wbs_element_idx');
            $table->dropIndex('pol_project_idx');
            $table->dropColumn(['wbs_element_id', 'project_id', 'account_assignment_type']);
        });
    }
};
