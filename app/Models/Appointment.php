<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'book_datetime',
        'start_datetime',
        'end_datetime',
        'location',
        'notes',
        'hash',
        'color',
        'status',
        'is_unavailability',
        'id_users_provider',
        'id_users_customer',
        'id_services',
        'id_google_calendar',
        'id_caldav_calendar',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'book_datetime' => 'datetime',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'is_unavailability' => 'boolean',
        'id_users_provider' => 'integer',
        'id_users_customer' => 'integer',
        'id_services' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'hash',
    ];

    /**
     * Boot method to generate hash automatically.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($appointment) {
            if (empty($appointment->hash)) {
                $appointment->hash = self::generateUniqueHash();
            }
        });

        // Fire events when status changes
        static::updating(function ($appointment) {
            if ($appointment->isDirty('status')) {
                $oldStatus = $appointment->getOriginal('status');
                $newStatus = $appointment->status;

                // Dispatch events
                // TODO: event(new AppointmentStatusChanged($appointment, $oldStatus, $newStatus));

                // Send notifications based on status
                if ($newStatus === 'confirmed') {
                    // Mail::to($appointment->customer)->send(new AppointmentConfirmed($appointment));
                } elseif ($newStatus === 'cancelled') {
                    // Mail::to($appointment->customer)->send(new AppointmentCancelled($appointment));
                }
            }
        });
    }

    /**
     * Generate a unique appointment hash with collision checking.
     *
     * Migrated from CI3 Appointments_model::generate_unique_hash()
     *
     * @param int $maxAttempts Maximum number of attempts to generate unique hash
     * @param int $hashLength Length of the hash to generate
     * @return string Unique hash
     * @throws \RuntimeException If unable to generate unique hash after max attempts
     */
    protected static function generateUniqueHash(int $maxAttempts = 10, int $hashLength = 16): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            // Generate random hash (16 bytes = 32 hex characters)
            $hash = bin2hex(random_bytes($hashLength));

            // Check if hash already exists in database
            $exists = static::where('hash', $hash)->exists();

            if (!$exists) {
                return $hash;
            }
        }

        // Fallback: use longer hash if all attempts failed
        // 20 bytes = 40 hex characters (increases uniqueness)
        $longerHash = bin2hex(random_bytes($hashLength + 4));

        // Final check - if this still exists, throw exception
        if (static::where('hash', $longerHash)->exists()) {
            throw new \RuntimeException(
                'Unable to generate unique appointment hash after ' . $maxAttempts . ' attempts.'
            );
        }

        return $longerHash;
    }

    /**
     * Relationships
     */

    /**
     * Get the provider (user) for this appointment.
     */
    public function provider()
    {
        return $this->belongsTo(User::class, 'id_users_provider');
    }

    /**
     * Get the customer (user) for this appointment.
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'id_users_customer');
    }

    /**
     * Get the service for this appointment.
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'id_services');
    }

    /**
     * Accessors & Mutators
     */

    /**
     * Get the appointment duration in minutes.
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->start_datetime && $this->end_datetime) {
            return $this->start_datetime->diffInMinutes($this->end_datetime);
        }
        return null;
    }

    /**
     * Check if appointment is in the past.
     */
    public function getIsPastAttribute(): bool
    {
        return $this->end_datetime && $this->end_datetime->isPast();
    }

    /**
     * Check if appointment is upcoming.
     */
    public function getIsUpcomingAttribute(): bool
    {
        return $this->start_datetime && $this->start_datetime->isFuture();
    }

    /**
     * Check if appointment is currently active.
     */
    public function getIsActiveAttribute(): bool
    {
        if ($this->start_datetime && $this->end_datetime) {
            return now()->between($this->start_datetime, $this->end_datetime);
        }
        return false;
    }

    /**
     * Get a human-readable time range.
     */
    public function getTimeRangeAttribute(): string
    {
        if ($this->start_datetime && $this->end_datetime) {
            return $this->start_datetime->format('g:i A') . ' - ' . $this->end_datetime->format('g:i A');
        }
        return '';
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to only include regular appointments (not unavailability).
     */
    public function scopeRegular($query)
    {
        return $query->where('is_unavailability', false);
    }

    /**
     * Scope a query to only include unavailability periods.
     */
    public function scopeUnavailability($query)
    {
        return $query->where('is_unavailability', true);
    }

    /**
     * Scope a query to only include upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>', now());
    }

    /**
     * Scope a query to only include past appointments.
     */
    public function scopePast($query)
    {
        return $query->where('end_datetime', '<', now());
    }

    /**
     * Scope a query to only include today's appointments.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('start_datetime', today());
    }

    /**
     * Scope a query to appointments within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where('start_datetime', '>=', $startDate)
                     ->where('start_datetime', '<=', $endDate);
    }

    /**
     * Scope a query to appointments for a specific provider.
     */
    public function scopeForProvider($query, $providerId)
    {
        return $query->where('id_users_provider', $providerId);
    }

    /**
     * Scope a query to appointments for a specific customer.
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('id_users_customer', $customerId);
    }

    /**
     * Scope a query to appointments for a specific service.
     */
    public function scopeForService($query, $serviceId)
    {
        return $query->where('id_services', $serviceId);
    }

    /**
     * Scope a query to appointments with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to confirmed appointments.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to pending appointments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to cancelled appointments.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to synced with Google Calendar.
     */
    public function scopeSyncedWithGoogle($query)
    {
        return $query->whereNotNull('id_google_calendar');
    }

    /**
     * Scope a query to synced with CalDAV.
     */
    public function scopeSyncedWithCalDav($query)
    {
        return $query->whereNotNull('id_caldav_calendar');
    }

    /**
     * Query Builder Methods (Migrated from CI3)
     */

    /**
     * Get all appointments that match the provided criteria.
     *
     * This method replicates the CI3 Appointments_model::get() functionality.
     * Returns only regular appointments (not unavailability periods) by default.
     *
     * @param array|string|null $where Where conditions (array or raw string)
     * @param int|null $limit Record limit
     * @param int|null $offset Record offset
     * @param string|null $orderBy Order by clause (e.g., 'start_datetime ASC')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAppointments(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null
    ) {
        $query = static::regular(); // Only get regular appointments (is_unavailability = false)

        // Apply where conditions
        if ($where !== null) {
            if (is_array($where)) {
                // Array conditions: ['id_users_provider' => 5, 'status' => 'confirmed']
                $query->where($where);
            } else {
                // Raw string conditions: "id_users_provider = 5 AND status = 'confirmed'"
                $query->whereRaw($where);
            }
        }

        // Apply ordering
        if ($orderBy) {
            // Parse order by string (e.g., "start_datetime ASC" or "id DESC")
            $parts = explode(' ', trim($orderBy));
            $column = $parts[0];
            $direction = $parts[1] ?? 'asc';
            $query->orderBy($column, $direction);
        }

        // Apply limit and offset
        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        return $query->get();
    }

    /**
     * Get all appointments including unavailability periods.
     *
     * Similar to getAppointments() but includes unavailability records.
     *
     * @param array|string|null $where Where conditions
     * @param int|null $limit Record limit
     * @param int|null $offset Record offset
     * @param string|null $orderBy Order by clause
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllAppointments(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null
    ) {
        $query = static::query();

        // Apply where conditions
        if ($where !== null) {
            if (is_array($where)) {
                $query->where($where);
            } else {
                $query->whereRaw($where);
            }
        }

        // Apply ordering
        if ($orderBy) {
            $parts = explode(' ', trim($orderBy));
            $column = $parts[0];
            $direction = $parts[1] ?? 'asc';
            $query->orderBy($column, $direction);
        }

        // Apply limit and offset
        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        return $query->get();
    }

    /**
     * Conflict Detection & Capacity Management
     */

    /**
     * Get the number of attendants for a specific service and provider during a time period.
     *
     * This counts how many appointments overlap with the given time slot for the same
     * service and provider. Used to check availability and prevent double-booking.
     *
     * Migrated from CI3 Appointments_model::get_attendants_number_for_period()
     *
     * @param \DateTime|\Carbon\Carbon $start Period start datetime
     * @param \DateTime|\Carbon\Carbon $end Period end datetime
     * @param int $serviceId Service ID
     * @param int $providerId Provider ID
     * @param int|null $excludeAppointmentId Exclude an appointment from the count (for updates)
     * @return int Number of overlapping appointments
     */
    public static function getAttendantsNumberForPeriod(
        \DateTime|\Carbon\Carbon $start,
        \DateTime|\Carbon\Carbon $end,
        int $serviceId,
        int $providerId,
        ?int $excludeAppointmentId = null
    ): int {
        $query = static::where('id_services', $serviceId)
            ->where('id_users_provider', $providerId)
            ->where(function ($query) use ($start, $end) {
                // Check for overlapping appointments
                // Case 1: Appointment starts before or at period start and ends after period start
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<=', $start)
                      ->where('end_datetime', '>', $start);
                })
                // Case 2: Appointment starts before period end and ends at or after period end
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $end)
                      ->where('end_datetime', '>=', $end);
                })
                // Case 3: Appointment is completely within the period
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '>=', $start)
                      ->where('end_datetime', '<=', $end);
                });
            });

        // Exclude specific appointment (useful when updating an existing appointment)
        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->count();
    }

    /**
     * Get the number of attendants for OTHER services during a time period.
     *
     * This counts how many appointments for different services overlap with the given
     * time slot for the same provider. Used to check if provider is busy with other services.
     *
     * Migrated from CI3 Appointments_model::get_other_service_attendants_number()
     *
     * @param \DateTime|\Carbon\Carbon $start Period start datetime
     * @param \DateTime|\Carbon\Carbon $end Period end datetime
     * @param int $serviceId Service ID (to exclude)
     * @param int $providerId Provider ID
     * @param int|null $excludeAppointmentId Exclude an appointment from the count (for updates)
     * @return int Number of overlapping appointments for other services
     */
    public static function getOtherServiceAttendantsNumber(
        \DateTime|\Carbon\Carbon $start,
        \DateTime|\Carbon\Carbon $end,
        int $serviceId,
        int $providerId,
        ?int $excludeAppointmentId = null
    ): int {
        $query = static::where('id_services', '!=', $serviceId) // Different service
            ->where('id_users_provider', $providerId)
            ->where(function ($query) use ($start, $end) {
                // Check for overlapping appointments (same logic as above)
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<=', $start)
                      ->where('end_datetime', '>', $start);
                })
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $end)
                      ->where('end_datetime', '>=', $end);
                })
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '>=', $start)
                      ->where('end_datetime', '<=', $end);
                });
            });

        // Exclude specific appointment
        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->count();
    }

    /**
     * Check if a time slot is available for a provider and service.
     *
     * Helper method that uses the attendants number methods to determine availability.
     *
     * @param \DateTime|\Carbon\Carbon $start Start datetime
     * @param \DateTime|\Carbon\Carbon $end End datetime
     * @param int $serviceId Service ID
     * @param int $providerId Provider ID
     * @param int|null $excludeAppointmentId Exclude appointment ID (for updates)
     * @return bool True if slot is available, false if conflicts exist
     */
    public static function isSlotAvailable(
        \DateTime|\Carbon\Carbon $start,
        \DateTime|\Carbon\Carbon $end,
        int $serviceId,
        int $providerId,
        ?int $excludeAppointmentId = null
    ): bool {
        // Check for conflicts with same service
        $sameServiceConflicts = static::getAttendantsNumberForPeriod(
            $start,
            $end,
            $serviceId,
            $providerId,
            $excludeAppointmentId
        );

        // Check for conflicts with other services
        $otherServiceConflicts = static::getOtherServiceAttendantsNumber(
            $start,
            $end,
            $serviceId,
            $providerId,
            $excludeAppointmentId
        );

        // Slot is available if no conflicts exist
        return $sameServiceConflicts === 0 && $otherServiceConflicts === 0;
    }

    /**
     * Get all conflicting appointments for a time period.
     *
     * Returns the actual appointment records that conflict, not just a count.
     *
     * @param \DateTime|\Carbon\Carbon $start Start datetime
     * @param \DateTime|\Carbon\Carbon $end End datetime
     * @param int $providerId Provider ID
     * @param int|null $excludeAppointmentId Exclude appointment ID
     * @return \Illuminate\Database\Eloquent\Collection Collection of conflicting appointments
     */
    public static function getConflictingAppointments(
        \DateTime|\Carbon\Carbon $start,
        \DateTime|\Carbon\Carbon $end,
        int $providerId,
        ?int $excludeAppointmentId = null
    ) {
        $query = static::where('id_users_provider', $providerId)
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<=', $start)
                      ->where('end_datetime', '>', $start);
                })
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $end)
                      ->where('end_datetime', '>=', $end);
                })
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '>=', $start)
                      ->where('end_datetime', '<=', $end);
                });
            });

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->with(['service', 'customer'])->get();
    }

    /**
     * Validation Methods
     */

    /**
     * Validate that the provider ID exists and has the provider role.
     *
     * @param int $providerId
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function validateProvider(int $providerId): bool
    {
        $exists = User::where('id', $providerId)
            ->whereHas('role', function ($query) {
                $query->where('slug', 'provider');
            })
            ->exists();

        if (!$exists) {
            throw new \InvalidArgumentException(
                "The appointment provider ID was not found in the database: {$providerId}"
            );
        }

        return true;
    }

    /**
     * Validate that the customer ID exists and has the customer role.
     *
     * @param int $customerId
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function validateCustomer(int $customerId): bool
    {
        $exists = User::where('id', $customerId)
            ->whereHas('role', function ($query) {
                $query->where('slug', 'customer');
            })
            ->exists();

        if (!$exists) {
            throw new \InvalidArgumentException(
                "The appointment customer ID was not found in the database: {$customerId}"
            );
        }

        return true;
    }

    /**
     * Validate that the service ID exists in the database.
     *
     * @param int $serviceId
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function validateService(int $serviceId): bool
    {
        $exists = Service::where('id', $serviceId)->exists();

        if (!$exists) {
            throw new \InvalidArgumentException('Appointment service id is invalid.');
        }

        return true;
    }

    /**
     * Validate all appointment foreign key relationships.
     *
     * @param array $data Appointment data to validate
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function validateAppointmentRelationships(array $data): bool
    {
        // Always validate provider
        if (!empty($data['id_users_provider'])) {
            self::validateProvider($data['id_users_provider']);
        }

        // Only validate customer and service for regular appointments (not unavailability)
        if (empty($data['is_unavailability']) || !$data['is_unavailability']) {
            if (!empty($data['id_users_customer'])) {
                self::validateCustomer($data['id_users_customer']);
            }

            if (!empty($data['id_services'])) {
                self::validateService($data['id_services']);
            }
        }

        return true;
    }

    /**
     * Helper Methods
     */

    /**
     * Check if appointment conflicts with another appointment.
     *
     * This is a simple instance method for checking conflicts between two appointments.
     * For more complex conflict checking, use the static conflict detection methods.
     */
    public function conflictsWith(Appointment $other): bool
    {
        return $this->start_datetime < $other->end_datetime
            && $this->end_datetime > $other->start_datetime
            && $this->id_users_provider === $other->id_users_provider;
    }

    /**
     * Cancel the appointment.
     *
     * @return bool True if successful, false otherwise
     * @throws \InvalidArgumentException If appointment cannot be cancelled
     */
    public function cancel(): bool
    {
        // Can't cancel completed or already cancelled appointments
        if ($this->isCompleted()) {
            throw new \InvalidArgumentException('Cannot cancel a completed appointment.');
        }

        if ($this->isCancelled()) {
            throw new \InvalidArgumentException('Appointment is already cancelled.');
        }

        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Confirm the appointment.
     *
     * @return bool True if successful, false otherwise
     * @throws \InvalidArgumentException If appointment cannot be confirmed
     */
    public function confirm(): bool
    {
        // Can only confirm pending appointments
        if (!$this->isPending()) {
            throw new \InvalidArgumentException(
                'Only pending appointments can be confirmed.'
            );
        }

        $this->status = 'confirmed';
        return $this->save();
    }

    /**
     * Mark appointment as completed.
     *
     * @return bool True if successful, false otherwise
     * @throws \InvalidArgumentException If appointment cannot be completed
     */
    public function complete(): bool
    {
        // Can only complete confirmed appointments
        if (!$this->isConfirmed()) {
            throw new \InvalidArgumentException(
                'Only confirmed appointments can be marked as completed.'
            );
        }

        // Can't complete future appointments
        if ($this->isUpcoming) {
            throw new \InvalidArgumentException(
                'Cannot complete an appointment that hasn\'t occurred yet.'
            );
        }

        $this->status = 'completed';
        return $this->save();
    }

    /**
     * Mark appointment as pending (initial status).
     *
     * @return bool True if successful, false otherwise
     */
    public function markPending(): bool
    {
        $this->status = 'pending';
        return $this->save();
    }

    /**
     * Check if appointment is confirmed.
     *
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if appointment is cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if appointment is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if appointment is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Search Methods
     */

    /**
     * Search appointments by keyword across multiple fields.
     *
     * Searches through appointment fields, service details, provider info, and customer info.
     * Migrated from CI3 Appointments_model::search()
     *
     * @param string $keyword Search keyword
     * @param int|null $limit Record limit
     * @param int|null $offset Record offset
     * @param string|null $orderBy Order by clause (e.g., 'start_datetime ASC')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function search(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null
    ) {
        $query = static::select('appointments.*')
        ->leftJoin('services', 'services.id', '=', 'appointments.id_services')
        ->join('users as providers', 'providers.id', '=', 'appointments.id_users_provider')
        ->leftJoin('users as customers', 'customers.id', '=', 'appointments.id_users_customer')
        ->where('appointments.is_unavailability', false)
        ->where(function ($q) use ($keyword) {
            // Search in appointment fields
            $q->where('appointments.start_datetime', 'LIKE', "%{$keyword}%")
              ->orWhere('appointments.end_datetime', 'LIKE', "%{$keyword}%")
              ->orWhere('appointments.location', 'LIKE', "%{$keyword}%")
              ->orWhere('appointments.hash', 'LIKE', "%{$keyword}%")
              ->orWhere('appointments.notes', 'LIKE', "%{$keyword}%")
              ->orWhere('appointments.status', 'LIKE', "%{$keyword}%")

              // Search in service fields
              ->orWhere('services.name', 'LIKE', "%{$keyword}%")
              ->orWhere('services.description', 'LIKE', "%{$keyword}%")

              // Search in provider fields
              ->orWhere('providers.first_name', 'LIKE', "%{$keyword}%")
              ->orWhere('providers.last_name', 'LIKE', "%{$keyword}%")
              ->orWhere('providers.email', 'LIKE', "%{$keyword}%")
              ->orWhere('providers.phone_number', 'LIKE', "%{$keyword}%")
              ->orWhere('providers.mobile_number', 'LIKE', "%{$keyword}%")

              // Search in customer fields
              ->orWhere('customers.first_name', 'LIKE', "%{$keyword}%")
              ->orWhere('customers.last_name', 'LIKE', "%{$keyword}%")
              ->orWhere('customers.email', 'LIKE', "%{$keyword}%")
              ->orWhere('customers.phone_number', 'LIKE', "%{$keyword}%")
              ->orWhere('customers.mobile_number', 'LIKE', "%{$keyword}%");
        });

        // Apply ordering
        if ($orderBy) {
            $parts = explode(' ', trim($orderBy));
            $column = $parts[0];
            $direction = $parts[1] ?? 'asc';
            $query->orderBy($column, $direction);
        } else {
            // Default ordering: most recent first
            $query->orderBy('appointments.start_datetime', 'desc');
        }

        // Apply limit and offset
        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        return $query->get();
    }

    /**
    * Advanced search with filters.
    *
    * More flexible search method that supports multiple search criteria.
    *
    * @param array $filters Search filters
    * @return \Illuminate\Database\Eloquent\Collection
    */
    public static function advancedSearch(array $filters = [])
    {
        $query = static::select('appointments.*')
            ->leftJoin('services', 'services.id', '=', 'appointments.id_services')
            ->join('users as providers', 'providers.id', '=', 'appointments.id_users_provider')
            ->leftJoin('users as customers', 'customers.id', '=', 'appointments.id_users_customer')
            ->where('appointments.is_unavailability', false);

        // Keyword search
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('appointments.location', 'LIKE', "%{$keyword}%")
                ->orWhere('appointments.notes', 'LIKE', "%{$keyword}%")
                ->orWhere('services.name', 'LIKE', "%{$keyword}%")
                ->orWhere('providers.first_name', 'LIKE', "%{$keyword}%")
                ->orWhere('providers.last_name', 'LIKE', "%{$keyword}%")
                ->orWhere('customers.first_name', 'LIKE', "%{$keyword}%")
                ->orWhere('customers.last_name', 'LIKE', "%{$keyword}%");
            });
        }

        // Provider filter
        if (!empty($filters['provider_id'])) {
            $query->where('appointments.id_users_provider', $filters['provider_id']);
        }

        // Customer filter
        if (!empty($filters['customer_id'])) {
            $query->where('appointments.id_users_customer', $filters['customer_id']);
        }

        // Service filter
        if (!empty($filters['service_id'])) {
            $query->where('appointments.id_services', $filters['service_id']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('appointments.status', $filters['status']);
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->whereDate('appointments.start_datetime', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('appointments.start_datetime', '<=', $filters['end_date']);
        }

        // Apply ordering
        $orderBy = $filters['order_by'] ?? 'appointments.start_datetime';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        // Apply pagination
        $limit = $filters['limit'] ?? 15;
        $offset = $filters['offset'] ?? 0;

        return $query->limit($limit)->offset($offset)->get();
    }

    /**
    * Scope for searching appointments (chainable with other scopes).
    *
    * @param \Illuminate\Database\Eloquent\Builder $query
    * @param string $keyword
    * @return \Illuminate\Database\Eloquent\Builder
    */
    public function scopeSearchByKeyword($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('start_datetime', 'LIKE', "%{$keyword}%")
            ->orWhere('end_datetime', 'LIKE', "%{$keyword}%")
            ->orWhere('location', 'LIKE', "%{$keyword}%")
            ->orWhere('hash', 'LIKE', "%{$keyword}%")
            ->orWhere('notes', 'LIKE', "%{$keyword}%")
            ->orWhere('status', 'LIKE', "%{$keyword}%");
        });
    }
}
