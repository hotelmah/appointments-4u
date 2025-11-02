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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Authentication fields (from ea_user_settings)
            $table->string('username', 256)->unique()->nullable();
            $table->string('email', 256)->unique();
            $table->string('password');

            // Profile fields
            $table->string('first_name', 256)->nullable();
            $table->string('last_name', 256)->nullable();
            $table->string('mobile_phone_number', 128)->nullable();
            $table->string('work_phone_number', 128)->nullable();
            $table->string('address', 256)->nullable();
            $table->string('city', 256)->nullable();
            $table->string('state', 128)->nullable();
            $table->string('zip_code', 64)->nullable();
            $table->text('notes')->nullable();

            // Preferences
            $table->string('timezone', 256)->default('UTC');
            $table->string('language', 256)->default('english');

            // Custom fields
            $table->text('custom_field_1')->nullable();
            $table->text('custom_field_2')->nullable();
            $table->text('custom_field_3')->nullable();
            $table->text('custom_field_4')->nullable();
            $table->text('custom_field_5')->nullable();

            // Privacy & LDAP
            $table->boolean('is_private')->default(false);
            $table->text('ldap_dn')->nullable();

            // Role relationship
            $table->foreignId('id_roles')
                ->nullable()
                ->constrained('roles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
