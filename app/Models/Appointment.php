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
                $appointment->hash = bin2hex(random_bytes(16));
            }
        });
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
     * Helper Methods
     */

    /**
     * Check if appointment conflicts with another appointment.
     */
    public function conflictsWith(Appointment $other): bool
    {
        return $this->start_datetime < $other->end_datetime
            && $this->end_datetime > $other->start_datetime
            && $this->id_users_provider === $other->id_users_provider;
    }

    /**
     * Cancel the appointment.
     */
    public function cancel(): bool
    {
        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Confirm the appointment.
     */
    public function confirm(): bool
    {
        $this->status = 'confirmed';
        return $this->save();
    }

    /**
     * Mark appointment as completed.
     */
    public function complete(): bool
    {
        $this->status = 'completed';
        return $this->save();
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
}
