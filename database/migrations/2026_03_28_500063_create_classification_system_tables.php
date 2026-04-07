<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('class_assignments');
        Schema::dropIfExists('class_characteristic_values');
        Schema::dropIfExists('class_characteristics');
        Schema::dropIfExists('classification_classes');

        Schema::create('classification_classes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('class_code', 30);
            $table->string('class_name', 100);
            $table->string('object_type', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'class_code', 'object_type'], 'cc_org_code_type_unq');
            $table->index(['organization_id', 'object_type'], 'cc_org_type_idx');
        });

        Schema::create('class_characteristics', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('cchar_org_fk');
            $table->foreignId('classification_class_id')->constrained('classification_classes')->name('cchar_class_fk');
            $table->string('characteristic_code', 30);
            $table->string('characteristic_name', 100);
            $table->enum('data_type', ['text', 'numeric', 'date', 'boolean', 'list'])->default('text');
            $table->string('unit_of_measure', 20)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->decimal('min_value', 18, 4)->nullable();
            $table->decimal('max_value', 18, 4)->nullable();
            $table->json('allowed_values')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['classification_class_id', 'characteristic_code'], 'cchar_class_code_unq');
        });

        Schema::create('class_characteristic_values', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('ccv_org_fk');
            $table->foreignId('class_characteristic_id')->constrained('class_characteristics')->name('ccv_char_fk');
            $table->string('object_type', 50);
            $table->unsignedBigInteger('object_id');
            $table->string('text_value', 500)->nullable();
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->date('date_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->timestamps();

            $table->unique(['class_characteristic_id', 'object_type', 'object_id'], 'ccv_char_obj_unq');
            $table->index(['object_type', 'object_id'], 'ccv_obj_idx');
        });

        Schema::create('class_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('ca_org_fk');
            $table->foreignId('classification_class_id')->constrained('classification_classes')->name('ca_class_fk');
            $table->string('object_type', 50);
            $table->unsignedBigInteger('object_id');
            $table->dateTime('assigned_at');
            $table->timestamps();

            $table->unique(['classification_class_id', 'object_type', 'object_id'], 'ca_class_obj_unq');
            $table->index(['object_type', 'object_id'], 'ca_obj_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_assignments');
        Schema::dropIfExists('class_characteristic_values');
        Schema::dropIfExists('class_characteristics');
        Schema::dropIfExists('classification_classes');
    }
};
