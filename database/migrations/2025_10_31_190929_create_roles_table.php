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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 256)->nullable();
            $table->string('slug', 256)->nullable()->unique();
            $table->boolean('is_admin')->nullable();
            $table->unsignedTinyInteger('appointments')->nullable();
            $table->unsignedTinyInteger('customers')->nullable();
            $table->unsignedTinyInteger('services')->nullable();
            $table->unsignedTinyInteger('users')->nullable();
            $table->unsignedTinyInteger('system_settings')->nullable();
            $table->unsignedTinyInteger('user_settings')->nullable();
            $table->unsignedTinyInteger('webhooks')->nullable();
            $table->unsignedTinyInteger('blocked_periods')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
