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
        Schema::create('services_providers', function (Blueprint $table) {
            $table->foreignId('id_users')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('id_services')
                ->constrained('services')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Composite primary key
            $table->primary(['id_users', 'id_services']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services_providers');
    }
};
