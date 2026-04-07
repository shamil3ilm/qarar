<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('revenue_account_determination_keys');
        Schema::dropIfExists('material_account_groups');
        Schema::dropIfExists('customer_account_groups');

        Schema::create('customer_account_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('group_code', 4)->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('material_account_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('group_code', 4)->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('revenue_account_determination_keys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('customer_account_group', 4)->nullable();
            $table->string('material_account_group', 4)->nullable();
            $table->string('condition_type', 4)->nullable();
            $table->unsignedBigInteger('gl_account_id');
            $table->foreign('gl_account_id', 'rev_acct_det_gl_fk')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });

        // Add customer_account_group_id to contacts
        if (!Schema::hasColumn('contacts', 'customer_account_group_id')) {
            Schema::table('contacts', function (Blueprint $table): void {
                $table->unsignedBigInteger('customer_account_group_id')->nullable()->after('contact_type');
                $table->foreign('customer_account_group_id', 'contact_cag_fk')
                    ->references('id')->on('customer_account_groups')->onDelete('set null');
            });
        }

        // Add material_account_group_id to products
        if (!Schema::hasColumn('products', 'material_account_group_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedBigInteger('material_account_group_id')->nullable()->after('category_id');
                $table->foreign('material_account_group_id', 'product_mag_fk')
                    ->references('id')->on('material_account_groups')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'material_account_group_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign('product_mag_fk');
                $table->dropColumn('material_account_group_id');
            });
        }
        if (Schema::hasColumn('contacts', 'customer_account_group_id')) {
            Schema::table('contacts', function (Blueprint $table): void {
                $table->dropForeign('contact_cag_fk');
                $table->dropColumn('customer_account_group_id');
            });
        }
        Schema::dropIfExists('revenue_account_determination_keys');
        Schema::dropIfExists('material_account_groups');
        Schema::dropIfExists('customer_account_groups');
    }
};
