<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 256)->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 32)->nullable();
            $table->text('description')->nullable();
            $table->string('color', 256)->default('#7cbae8');
            $table->text('location')->nullable();
            $table->string('availabilities_type', 32)->default('flexible');
            $table->unsignedInteger('attendants_number')->default(1);
            $table->boolean('is_private')->default(false);

            $table->foreignId('id_service_categories')
                ->nullable()
                ->constrained('service_categories')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
