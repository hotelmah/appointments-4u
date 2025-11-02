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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 256)->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('actions')->nullable();
            $table->string('secret_header', 256)->default('X-Ea-Token');
            $table->string('secret_token', 512)->nullable();
            $table->boolean('is_ssl_verified')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
