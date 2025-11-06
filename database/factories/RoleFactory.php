<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Administrator',
            'Provider',
            'Secretary',
            'Customer',
            'Manager',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_admin' => false,

            // Permission levels: 0 = none, 1 = view, 2 = add, 3 = edit, 4 = delete
            'appointments' => fake()->numberBetween(0, 4),
            'customers' => fake()->numberBetween(0, 4),
            'services' => fake()->numberBetween(0, 4),
            'users' => fake()->numberBetween(0, 4),
            'system_settings' => fake()->numberBetween(0, 4),
            'user_settings' => fake()->numberBetween(0, 4),
            'webhooks' => fake()->numberBetween(0, 4),
            'blocked_periods' => fake()->numberBetween(0, 4),
        ];
    }

    /**
     * Create an administrator role with full permissions.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_admin' => true,
            'appointments' => 15,      // Full CRUD
            'customers' => 15,         // Full CRUD
            'services' => 15,          // Full CRUD
            'users' => 15,            // Full CRUD
            'system_settings' => 15,  // Full CRUD
            'user_settings' => 15,    // Full CRUD
            'webhooks' => 15,         // Full CRUD
            'blocked_periods' => 15,  // Full CRUD
        ]);
    }

    /**
     * Create a provider role with appointment and customer management.
     */
    public function provider(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Provider',
            'slug' => 'provider',
            'is_admin' => false,
            'appointments' => 15,      // Full CRUD on own appointments
            'customers' => 7,          // View, add, edit customers
            'services' => 1,           // View only
            'users' => 0,              // No access
            'system_settings' => 0,    // No access
            'user_settings' => 7,      // Manage own settings
            'webhooks' => 0,           // No access
            'blocked_periods' => 7,    // View, add, edit own blocks
        ]);
    }

    /**
     * Create a secretary role with administrative support permissions.
     */
    public function secretary(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Secretary',
            'slug' => 'secretary',
            'is_admin' => false,
            'appointments' => 15,      // Full CRUD for providers they manage
            'customers' => 15,         // Full CRUD
            'services' => 1,           // View only
            'users' => 1,              // View only
            'system_settings' => 0,    // No access
            'user_settings' => 7,      // Manage own settings
            'webhooks' => 0,           // No access
            'blocked_periods' => 7,    // View, add, edit
        ]);
    }

    /**
     * Create a customer role with limited booking permissions.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Customer',
            'slug' => 'customer',
            'is_admin' => false,
            'appointments' => 3,       // View and add own appointments
            'customers' => 0,          // No access to other customers
            'services' => 1,           // View available services
            'users' => 0,              // No access
            'system_settings' => 0,    // No access
            'user_settings' => 7,      // Manage own settings
            'webhooks' => 0,           // No access
            'blocked_periods' => 1,    // View only (to see availability)
        ]);
    }

    /**
     * Create a manager role with elevated permissions.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Manager',
            'slug' => 'manager',
            'is_admin' => false,
            'appointments' => 15,      // Full CRUD
            'customers' => 15,         // Full CRUD
            'services' => 15,          // Full CRUD
            'users' => 7,              // View, add, edit (not delete)
            'system_settings' => 7,    // View, add, edit
            'user_settings' => 15,     // Full CRUD
            'webhooks' => 7,           // View, add, edit
            'blocked_periods' => 15,   // Full CRUD
        ]);
    }

    /**
     * Create a role with no permissions (disabled).
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'appointments' => 0,
            'customers' => 0,
            'services' => 0,
            'users' => 0,
            'system_settings' => 0,
            'user_settings' => 0,
            'webhooks' => 0,
            'blocked_periods' => 0,
        ]);
    }

    /**
     * Create a role with custom permission level for all modules.
     */
    public function withPermissionLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'appointments' => $level,
            'customers' => $level,
            'services' => $level,
            'users' => $level,
            'system_settings' => $level,
            'user_settings' => $level,
            'webhooks' => $level,
            'blocked_periods' => $level,
        ]);
    }
}
