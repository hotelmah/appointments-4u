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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->dateTime('book_datetime')->nullable();
            $table->dateTime('start_datetime')->nullable();
            $table->dateTime('end_datetime')->nullable();
            $table->text('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('hash')->nullable()->index();
            $table->string('color', 256)->default('#7cbae8');
            $table->string('status', 512)->default('');
            $table->boolean('is_unavailability')->default(false);

            // Foreign keys
            $table->foreignId('id_users_provider')->nullable()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('id_users_customer')->nullable()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('id_services')->nullable()->constrained('services')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('id_google_calendar')->nullable();
            $table->string('id_caldav_calendar')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
