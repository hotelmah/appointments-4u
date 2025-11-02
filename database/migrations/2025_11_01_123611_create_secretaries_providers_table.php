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
        Schema::create('secretaries_providers', function (Blueprint $table) {
            $table->foreignId('id_users_secretary')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('id_users_provider')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Composite primary key
            $table->primary(['id_users_secretary', 'id_users_provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secretaries_providers');
    }
};
