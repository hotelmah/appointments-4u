<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate appointment start time (random future date/time during business hours)
        $startDateTime = fake()->dateTimeBetween('now', '+60 days')
            ->setTime(
                fake()->numberBetween(9, 16), // 9 AM to 4 PM
                fake()->randomElement([0, 15, 30, 45]), // 15-minute intervals
                0
            );

        // Duration in minutes (typically 30, 60, or 90 minutes)
        $durationMinutes = fake()->randomElement([30, 60, 90]);

        // Calculate end time
        $endDateTime = (clone $startDateTime)->modify("+{$durationMinutes} minutes");

        // Book datetime is usually before the start (when customer made the booking)
        $bookDateTime = (clone $startDateTime)->modify('-' . fake()->numberBetween(1, 30) . ' days');

        return [
            'book_datetime' => $bookDateTime,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'location' => fake()->optional(0.7)->randomElement([
                'Main Office',
                'Room 101',
                'Conference Room A',
                'Online - Zoom',
                fake()->address(),
            ]),
            'notes' => fake()->optional(0.4)->sentence(10),
            'hash' => bin2hex(random_bytes(16)), // 32-character hex hash
            'color' => fake()->randomElement([
                '#7cbae8', // Default blue
                '#3788d8',
                '#56ca85',
                '#f5a331',
                '#e85642',
            ]),
            'status' => fake()->randomElement([
                'booked',
                'confirmed',
                'pending',
                'completed',
                'cancelled',
            ]),
            'is_unavailability' => false, // Regular appointments (not unavailability blocks)

            // Foreign keys - will be set by relationships or overridden
            'id_users_provider' => User::factory(),
            'id_users_customer' => User::factory(),
            'id_services' => Service::factory(),

            // Calendar sync IDs (usually populated when synced)
            'id_google_calendar' => fake()->optional(0.2)->regexify('[a-z0-9]{26}'),
            'id_caldav_calendar' => fake()->optional(0.1)->uuid(),
        ];
    }

    /**
     * Indicate that the appointment is an unavailability period.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_unavailability' => true,
            'id_users_customer' => null, // No customer for unavailability
            'id_services' => null, // No service for unavailability
            'status' => '',
            'notes' => fake()->randomElement([
                'Lunch Break',
                'Out of Office',
                'Personal Time',
                'Meeting',
                'Training',
            ]),
        ]);
    }

    /**
     * Indicate that the appointment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Indicate that the appointment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the appointment is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the appointment is completed (in the past).
     */
    public function completed(): static
    {
        $completedDateTime = fake()->dateTimeBetween('-60 days', '-1 day')
            ->setTime(
                fake()->numberBetween(9, 16),
                fake()->randomElement([0, 15, 30, 45]),
                0
            );

        $durationMinutes = fake()->randomElement([30, 60, 90]);
        $endDateTime = (clone $completedDateTime)->modify("+{$durationMinutes} minutes");
        $bookDateTime = (clone $completedDateTime)->modify('-' . fake()->numberBetween(1, 30) . ' days');

        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'book_datetime' => $bookDateTime,
            'start_datetime' => $completedDateTime,
            'end_datetime' => $endDateTime,
        ]);
    }

    /**
     * Indicate that the appointment is synced with Google Calendar.
     */
    public function withGoogleSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'id_google_calendar' => fake()->regexify('[a-z0-9]{26}'),
        ]);
    }

    /**
     * Indicate that the appointment is synced with CalDAV.
     */
    public function withCalDavSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'id_caldav_calendar' => fake()->uuid(),
        ]);
    }

    /**
     * Set a specific provider for the appointment.
     */
    public function forProvider(User $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'id_users_provider' => $provider->id,
        ]);
    }

    /**
     * Set a specific customer for the appointment.
     */
    public function forCustomer(User $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'id_users_customer' => $customer->id,
        ]);
    }

    /**
     * Set a specific service for the appointment.
     */
    public function forService(Service $service): static
    {
        return $this->state(fn (array $attributes) => [
            'id_services' => $service->id,
        ]);
    }
}
