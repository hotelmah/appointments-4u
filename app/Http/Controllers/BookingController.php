<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Display a listing of appointments.
     */
    public function index(Request $request)
    {
        $query = Appointment::regular()
            ->with(['provider', 'customer', 'service']);

        // Apply filters
        if ($request->has('provider_id')) {
            $query->forProvider($request->provider_id);
        }

        if ($request->has('customer_id')) {
            $query->forCustomer($request->customer_id);
        }

        if ($request->has('status')) {
            $query->withStatus($request->status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->betweenDates($request->start_date, $request->end_date);
        }

        // Order by start datetime
        $appointments = $query->orderBy('start_datetime', 'asc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'appointments' => $appointments
        ]);
    }

    /**
     * Display a specific appointment.
     */
    public function show(int $id)
    {
        try {
            $appointment = Appointment::with(['provider', 'customer', 'service'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'appointment' => $appointment
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Appointment not found.'
            ], 404);
        }
    }

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

            // Set book_datetime to now (replaces CI3 insert() logic)
            $data['book_datetime'] = now();

            // Validate appointment data
            $this->validateAppointmentData($data);

            // Validate database relationships
            Appointment::validateAppointmentRelationships($data);

            $start = Carbon::parse($data['start_datetime']);
            $end = Carbon::parse($data['end_datetime']);

            // Check if slot is available
            $isAvailable = Appointment::isSlotAvailable(
                $start,
                $end,
                $data['id_services'],
                $data['id_users_provider']
            );

            if (!$isAvailable) {
                return response()->json([
                    'success' => false,
                    'error' => 'This time slot is not available. The provider is already booked.'
                ], 422);
            }

            // Create the appointment (Laravel handles timestamps & hash automatically)
            $appointment = Appointment::create($data);

            // Load relationships for response
            $appointment->load(['provider', 'customer', 'service']);

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
            // Laravel automatically handles updated_at timestamp
            $appointment = Appointment::findOrFail($id);

            // Basic validation rules
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

            // Merge with existing data for validation
            $dataToValidate = array_merge($appointment->toArray(), $data);

            // Validate appointment data
            $this->validateAppointmentData($dataToValidate, $id);

            // Validate database relationships if foreign keys are being updated
            if (isset($data['id_users_provider']) || isset($data['id_users_customer']) || isset($data['id_services'])) {
                Appointment::validateAppointmentRelationships($dataToValidate);
            }

            if (isset($data['start_datetime']) || isset($data['end_datetime'])) {
                $start = Carbon::parse($dataToValidate['start_datetime']);
                $end = Carbon::parse($dataToValidate['end_datetime']);

                // Check availability, excluding current appointment
                $isAvailable = Appointment::isSlotAvailable(
                    $start,
                    $end,
                    $dataToValidate['id_services'],
                    $dataToValidate['id_users_provider'],
                    $id // Exclude this appointment from conflict check
                );

                if (!$isAvailable) {
                    return response()->json([
                        'success' => false,
                        'error' => 'This time slot is not available.'
                    ], 422);
                }
            }

            // Update the appointment
            $appointment->update($data);

            // Reload relationships
            $appointment->load(['provider', 'customer', 'service']);

            return response()->json([
                'success' => true,
                'id' => $appointment->id,
                'appointment' => $appointment
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Appointment not found.'
            ], 404);
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
     * Delete an appointment.
     */
    public function destroy(int $id)
    {
        try {
            $appointment = Appointment::findOrFail($id);
            $appointment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Appointment deleted successfully.'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Appointment not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while deleting the appointment.'
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

        // Check for appointment conflicts
        if (!empty($data['start_datetime']) && !empty($data['end_datetime'])) {
            $start = Carbon::parse($data['start_datetime']);
            $end = Carbon::parse($data['end_datetime']);

            $isAvailable = Appointment::isSlotAvailable(
                $start,
                $end,
                $data['id_services'],
                $data['id_users_provider'],
                $appointmentId // Exclude current appointment when updating
            );

            if (!$isAvailable) {
                throw new \InvalidArgumentException(
                    'This time slot is not available. The provider has conflicting appointments.'
                );
            }
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

    /**
     * Check availability for a time slot (without creating an appointment).
     *
     * This endpoint allows checking if a slot is available before attempting to book.
     * Useful for frontend validation and showing available time slots.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAvailability(Request $request)
    {
        try {
            $data = $request->validate([
                'start_datetime' => 'required|date',
                'end_datetime' => 'required|date|after:start_datetime',
                'id_users_provider' => 'required|integer',
                'id_services' => 'required|integer',
                'exclude_appointment_id' => 'nullable|integer', // For checking when editing
            ]);

            $start = Carbon::parse($data['start_datetime']);
            $end = Carbon::parse($data['end_datetime']);

            // Check if slot is available
            $isAvailable = Appointment::isSlotAvailable(
                $start,
                $end,
                $data['id_services'],
                $data['id_users_provider'],
                $data['exclude_appointment_id'] ?? null
            );

            // Get conflict counts
            $sameServiceCount = Appointment::getAttendantsNumberForPeriod(
                $start,
                $end,
                $data['id_services'],
                $data['id_users_provider'],
                $data['exclude_appointment_id'] ?? null
            );

            $otherServiceCount = Appointment::getOtherServiceAttendantsNumber(
                $start,
                $end,
                $data['id_services'],
                $data['id_users_provider'],
                $data['exclude_appointment_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'available' => $isAvailable,
                'conflicts' => [
                    'same_service' => $sameServiceCount,
                    'other_services' => $otherServiceCount,
                    'total' => $sameServiceCount + $otherServiceCount
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while checking availability.'
            ], 500);
        }
    }

    /**
     * Get detailed conflict information for a time slot.
     *
     * Returns the actual conflicting appointments with details about
     * service, customer, and time for each conflict.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConflicts(Request $request)
    {
        try {
            $data = $request->validate([
                'start_datetime' => 'required|date',
                'end_datetime' => 'required|date|after:start_datetime',
                'id_users_provider' => 'required|integer',
                'exclude_appointment_id' => 'nullable|integer',
            ]);

            $start = Carbon::parse($data['start_datetime']);
            $end = Carbon::parse($data['end_datetime']);

            // Get all conflicting appointments
            $conflicts = Appointment::getConflictingAppointments(
                $start,
                $end,
                $data['id_users_provider'],
                $data['exclude_appointment_id'] ?? null
            );

            // Format the response
            $formattedConflicts = $conflicts->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'start_datetime' => $appointment->start_datetime->toIso8601String(),
                    'end_datetime' => $appointment->end_datetime->toIso8601String(),
                    'time_range' => $appointment->time_range,
                    'service' => [
                        'id' => $appointment->service->id ?? null,
                        'name' => $appointment->service->name ?? 'N/A',
                    ],
                    'customer' => [
                        'id' => $appointment->customer->id ?? null,
                        'name' => $appointment->customer->first_name . ' ' . $appointment->customer->last_name ?? 'N/A',
                    ],
                    'status' => $appointment->status,
                    'is_unavailability' => $appointment->is_unavailability,
                ];
            });

            return response()->json([
                'success' => true,
                'conflict_count' => $conflicts->count(),
                'has_conflicts' => $conflicts->isNotEmpty(),
                'conflicts' => $formattedConflicts
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while fetching conflicts.'
            ], 500);
        }
    }

    /**
     * Get appointment statistics for a provider.
     *
     * Returns counts and statistics about appointments for analysis.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProviderStatistics(Request $request)
    {
        try {
            $data = $request->validate([
                'id_users_provider' => 'required|integer',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $providerId = $data['id_users_provider'];
            $query = Appointment::forProvider($providerId);

            // Apply date range if provided
            if (!empty($data['start_date']) && !empty($data['end_date'])) {
                $query->betweenDates($data['start_date'], $data['end_date']);
            }

            // Get statistics
            $statistics = [
                'total_appointments' => $query->count(),
                'confirmed' => (clone $query)->confirmed()->count(),
                'pending' => (clone $query)->pending()->count(),
                'cancelled' => (clone $query)->cancelled()->count(),
                'upcoming' => (clone $query)->upcoming()->count(),
                'past' => (clone $query)->past()->count(),
                'today' => (clone $query)->today()->count(),
            ];

            return response()->json([
                'success' => true,
                'provider_id' => $providerId,
                'statistics' => $statistics,
                'date_range' => [
                    'start' => $data['start_date'] ?? null,
                    'end' => $data['end_date'] ?? null,
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while fetching statistics.'
            ], 500);
        }
    }

    /**
     * Cancel an appointment.
     */
    public function cancelAppointment(int $id)
    {
        try {
            $appointment = Appointment::findOrFail($id);

            if ($appointment->cancel()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Appointment cancelled successfully.'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel appointment.'
            ], 500);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Appointment not found.'
            ], 404);
        }
    }

    /**
     * Confirm an appointment.
     */
    public function confirmAppointment(int $id)
    {
        $appointment = Appointment::findOrFail($id);

        // Can add business logic here
        if ($appointment->isPending()) {
            $appointment->confirm();

            // Send confirmation email
            // Mail::to($appointment->customer)->send(new AppointmentConfirmed($appointment));

            return response()->json([
                'success' => true,
                'message' => 'Appointment confirmed and notification sent.'
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Appointment is not in pending status.'
        ], 422);
    }

    /**
     * Search appointments by keyword.
     *
     * Searches across appointment details, services, providers, and customers.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $data = $request->validate([
                'keyword' => 'required|string|min:2',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
                'order_by' => 'nullable|string',
            ]);

            $keyword = $data['keyword'];
            $limit = $data['limit'] ?? 15;
            $offset = $data['offset'] ?? 0;
            $orderBy = $data['order_by'] ?? 'start_datetime DESC';

            // Perform search
            $appointments = Appointment::search(
                $keyword,
                $limit,
                $offset,
                $orderBy
            );

            // Load relationships
            $appointments->load(['provider', 'customer', 'service']);

            return response()->json([
                'success' => true,
                'keyword' => $keyword,
                'count' => $appointments->count(),
                'appointments' => $appointments
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while searching appointments.'
            ], 500);
        }
    }

    /**
     * Advanced search with multiple filters.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function advancedSearch(Request $request)
    {
        try {
            $filters = $request->validate([
                'keyword' => 'nullable|string|min:2',
                'provider_id' => 'nullable|integer',
                'customer_id' => 'nullable|integer',
                'service_id' => 'nullable|integer',
                'status' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'order_by' => 'nullable|string',
                'order_direction' => 'nullable|in:asc,desc',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $appointments = Appointment::advancedSearch($filters);

            // Load relationships
            $appointments->load(['provider', 'customer', 'service']);

            return response()->json([
                'success' => true,
                'filters' => $filters,
                'count' => $appointments->count(),
                'appointments' => $appointments
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred during advanced search.'
            ], 500);
        }
    }
}
