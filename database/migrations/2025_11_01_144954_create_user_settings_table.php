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
        Schema::create('user_settings', function (Blueprint $table) {
            // One-to-one relationship with users table
            $table->foreignId('id_users')
                ->primary()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Working schedule (JSON would be better for working_plan)
            $table->json('working_plan')->nullable();
            $table->json('working_plan_exceptions')->nullable();

            // Notification preferences
            $table->boolean('notifications')->nullable();

            // Google Calendar integration
            $table->boolean('google_sync')->nullable();
            $table->text('google_token')->nullable();
            $table->string('google_calendar', 128)->nullable();

            // CalDAV integration
            $table->boolean('caldav_sync')->default(false);
            $table->string('caldav_url', 512)->nullable();
            $table->string('caldav_username', 256)->nullable();
            $table->string('caldav_password', 256)->nullable(); // Consider encryption

            // Sync preferences
            $table->unsignedInteger('sync_past_days')->default(30);
            $table->unsignedInteger('sync_future_days')->default(90);

            // UI preferences
            $table->string('calendar_view', 32)->default('default');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
