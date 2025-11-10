<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Using model constants for realistic setting names
        $settingNames = [
            Setting::COMPANY_NAME,
            Setting::COMPANY_EMAIL,
            Setting::DATE_FORMAT,
            Setting::TIME_FORMAT,
            Setting::REQUIRE_PHONE_NUMBER,
            Setting::CUSTOMER_NOTIFICATIONS,
            Setting::BOOK_ADVANCE_TIMEOUT,
            Setting::FUTURE_BOOKING_LIMIT,
        ];

        return [
            // From migration: string(512) unique
            // From model: fillable 'name'
            'name' => fake()->unique()->randomElement($settingNames),

            // From migration: longText nullable
            // From model: fillable 'value', can be string/json
            'value' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the setting is for company information.
     */
    public function companyInfo(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Setting::COMPANY_NAME,
            'value' => fake()->company(),
        ]);
    }

    /**
     * Indicate that the setting is a boolean value.
     */
    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => fake()->randomElement(['0', '1']),
        ]);
    }

    /**
     * Indicate that the setting is an integer value.
     */
    public function integer(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => (string) fake()->numberBetween(1, 365),
        ]);
    }

    /**
     * Indicate that the setting is a date format.
     */
    public function dateFormat(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Setting::DATE_FORMAT,
            'value' => fake()->randomElement(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y']),
        ]);
    }

    /**
     * Indicate that the setting is a time format.
     */
    public function timeFormat(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Setting::TIME_FORMAT,
            'value' => fake()->randomElement(['H:i', 'h:i A', 'h:i a']),
        ]);
    }

    /**
     * Indicate that the setting has a long text value.
     */
    public function longText(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => fake()->paragraphs(5, true),
        ]);
    }

    /**
     * Indicate that the setting is null/empty.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => null,
        ]);
    }
}
