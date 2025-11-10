<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Store a new appointment.
     */
    public function store(Request $request)
    {
        try {
            // Basic validation rules
            $data = $request->validate([
                'start_datetime' => 'required|date',
                'end_datetime' => 'required|date|after:start_datetime',
                'id_users_provider' => 'required|integer',
                'id_users_customer' => 'nullable|integer',
                'id_services' => 'nullable|integer',
                'location' => 'nullable|string',
                'notes' => $this->getNotesValidationRule(),
                'color' => 'nullable|string|max:256',
                'status' => 'nullable|string|max:512',
                'is_unavailability' => 'boolean',
            ]);

            // Validate appointment data
            $this->validateAppointmentData($data);

            // Validate database relationships
            Appointment::validateAppointmentRelationships($data);

            // Create the appointment
            $appointment = Appointment::create($data);

            return response()->json([
                'success' => true,
                'id' => $appointment->id,
                'appointment' => $appointment
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while creating the appointment.'
            ], 500);
        }
    }

    /**
     * Update an existing appointment.
     */
    public function update(Request $request, int $id)
    {
        try {
            // Check if appointment exists
            $appointment = Appointment::findOrFail($id);

            // Basic validation rules (using 'sometimes' for updates)
            $data = $request->validate([
                'start_datetime' => 'sometimes|date',
                'end_datetime' => 'sometimes|date|after:start_datetime',
                'id_users_provider' => 'sometimes|integer',
                'id_users_customer' => 'nullable|integer',
                'id_services' => 'nullable|integer',
                'location' => 'nullable|string',
                'notes' => $this->getNotesValidationRule(),
                'color' => 'nullable|string|max:256',
                'status' => 'nullable|string|max:512',
                'is_unavailability' => 'boolean',
            ]);

            // Merge with existing data for complete validation
            $dataToValidate = array_merge($appointment->toArray(), $data);

            // Validate appointment data
            $this->validateAppointmentData($dataToValidate, $id);

            // Validate database relationships if foreign keys are being updated
            if (isset($data['id_users_provider']) || isset($data['id_users_customer']) || isset($data['id_services'])) {
                Appointment::validateAppointmentRelationships($dataToValidate);
            }

            $appointment->update($data);

            return response()->json([
                'success' => true,
                'id' => $appointment->id,
                'appointment' => $appointment
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating the appointment.'
            ], 500);
        }
    }

    /**
     * Validate appointment-specific business rules.
     *
     * This method contains the validation logic from CI3's Appointments_model::validate()
     * that goes beyond basic field validation.
     *
     * @param array $data Appointment data to validate
     * @param int|null $appointmentId Appointment ID (for updates)
     * @throws \InvalidArgumentException
     */
    protected function validateAppointmentData(array $data, ?int $appointmentId = null): void
    {
        // For unavailability periods, skip customer and service validation
        $isUnavailability = !empty($data['is_unavailability']) && $data['is_unavailability'];

        // Make sure all required fields are provided
        if (!$isUnavailability) {
            if (empty($data['id_services'])) {
                throw new \InvalidArgumentException('Service is required for regular appointments.');
            }

            if (empty($data['id_users_customer'])) {
                throw new \InvalidArgumentException('Customer is required for regular appointments.');
            }
        }

        // Provider is always required
        if (empty($data['id_users_provider'])) {
            throw new \InvalidArgumentException('Provider is required for all appointments.');
        }

        // Validate date/time values
        if (empty($data['start_datetime']) || empty($data['end_datetime'])) {
            throw new \InvalidArgumentException('Start and end date/time are required.');
        }

        // Validate that start_datetime is a valid date
        try {
            $startDateTime = Carbon::parse($data['start_datetime']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('The appointment start date time is invalid.');
        }

        // Validate that end_datetime is a valid date
        try {
            $endDateTime = Carbon::parse($data['end_datetime']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('The appointment end date time is invalid.');
        }

        // Make sure end is after start
        if ($endDateTime <= $startDateTime) {
            throw new \InvalidArgumentException('The appointment end time must be after the start time.');
        }

        // Make sure the appointment lasts longer than the minimum duration
        $minimumDuration = config('appointments.minimum_duration', 15);
        $durationMinutes = $startDateTime->diffInMinutes($endDateTime);

        if ($durationMinutes < $minimumDuration) {
            throw new \InvalidArgumentException(
                "The appointment duration cannot be less than {$minimumDuration} minutes."
            );
        }
    }

    /**
     * Get validation rules for notes field based on settings.
     *
     * Uses the Setting model to check if notes are required.
     *
     * @return string
     */
    protected function getNotesValidationRule(): string
    {
        // Use the Setting model's method to check if notes are required
        $requireNotes = Setting::getBool('require_notes', false);

        return $requireNotes ? 'required|string' : 'nullable|string';
    }
}
