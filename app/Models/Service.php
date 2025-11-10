<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Service extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'duration',
        'price',
        'currency',
        'description',
        'color',
        'location',
        'availabilities_type',
        'attendants_number',
        'is_private',
        'id_service_categories',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'attendants_number' => 'integer',
        'is_private' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => '#7cbae8',
        'availabilities_type' => 'flexible',
        'attendants_number' => 1,
        'is_private' => false,
    ];

    /**
     * Relationships
     */

    /**
     * Get the category that the service belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'id_service_categories');
    }

    /**
     * Get the providers (users) that can offer this service.
     * Many-to-many relationship through services_providers pivot table.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function providers()
    {
        return $this->belongsToMany(
            User::class,
            'services_providers',
            'id_services',
            'id_users'
        );
    }

    /**
     * Get the appointments for this service.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'id_services');
    }

    /**
     * Accessors & Mutators
     */

    /**
     * Get the formatted price with currency.
     *
     * @return string
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === null) {
            return 'N/A';
        }

        $currencySymbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
        ];

        $symbol = $currencySymbols[$this->currency] ?? $this->currency;

        return $symbol . number_format($this->price, 2);
    }

    /**
     * Get the duration in hours and minutes format.
     *
     * @return string
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) {
            return 'N/A';
        }

        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get the service name with category.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        if ($this->category) {
            return "{$this->category->name} - {$this->name}";
        }
        return $this->name;
    }

    /**
     * Check if service allows multiple attendants.
     *
     * @return bool
     */
    public function getAllowsMultipleAttendantsAttribute(): bool
    {
        return $this->attendants_number > 1;
    }

    /**
     * Get availability type display name.
     *
     * @return string
     */
    public function getAvailabilityTypeDisplayAttribute(): string
    {
        return match ($this->availabilities_type) {
            'flexible' => 'Flexible Schedule',
            'fixed' => 'Fixed Schedule',
            default => ucfirst($this->availabilities_type),
        };
    }

    /**
     * Get the number of bookings for this service.
     *
     * @return int
     */
    public function getBookingsCountAttribute(): int
    {
        return $this->appointments()->count();
    }

    /**
     * Get active bookings (upcoming and not cancelled).
     *
     * @return int
     */
    public function getActiveBookingsCountAttribute(): int
    {
        return $this->appointments()
            ->where('start_datetime', '>', now())
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to only include public services.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope a query to only include private services.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope a query to services by category.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $categoryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('id_service_categories', $categoryId);
    }

    /**
     * Scope a query to services within a price range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $minPrice
     * @param float $maxPrice
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePriceBetween($query, $minPrice, $maxPrice)
    {
        return $query->whereBetween('price', [$minPrice, $maxPrice]);
    }

    /**
     * Scope a query to services by duration.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $duration
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDuration($query, $duration)
    {
        return $query->where('duration', $duration);
    }

    /**
     * Scope a query to services with flexible availability.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFlexible($query)
    {
        return $query->where('availabilities_type', 'flexible');
    }

    /**
     * Scope a query to services with fixed availability.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFixed($query)
    {
        return $query->where('availabilities_type', 'fixed');
    }

    /**
     * Scope a query to services that allow multiple attendants.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAllowsMultipleAttendants($query)
    {
        return $query->where('attendants_number', '>', 1);
    }

    /**
     * Scope a query to services offered by a specific provider.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $providerId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfferedBy($query, $providerId)
    {
        return $query->whereHas('providers', function ($q) use ($providerId) {
            $q->where('id_users', $providerId);
        });
    }

    /**
     * Scope a query to services by currency.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope a query to search services by name or description.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Helper Methods
     */

    /**
     * Check if service is available for booking.
     *
     * @return bool
     */
    public function isAvailableForBooking(): bool
    {
        // Check if service has at least one provider
        return $this->providers()->exists();
    }

    /**
     * Check if service can accommodate additional attendants.
     *
     * @param int $requestedAttendants
     * @return bool
     */
    public function canAccommodateAttendants(int $requestedAttendants): bool
    {
        return $requestedAttendants <= $this->attendants_number;
    }

    /**
     * Get available providers for this service.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableProviders()
    {
        return $this->providers()
            ->where('is_private', false)
            ->get();
    }

    /**
     * Calculate end time based on start time and service duration.
     *
     * @param string|\Carbon\Carbon $startDateTime
     * @return \Carbon\Carbon
     */
    public function calculateEndTime($startDateTime): Carbon
    {
        $start = is_string($startDateTime)
            ? Carbon::parse($startDateTime)
            : $startDateTime;

        return $start->copy()->addMinutes($this->duration);
    }

    /**
     * Get upcoming appointments for this service.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUpcomingAppointments()
    {
        return $this->appointments()
            ->where('start_datetime', '>', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_datetime')
            ->get();
    }

    /**
     * Check if service has available slots for a given date.
     *
     * @param string|\Carbon\Carbon $date
     * @return bool
     */
    public function hasAvailableSlots($date): bool
    {
        $date = is_string($date) ? Carbon::parse($date) : $date;

        // Get appointments for this service on the date
        $appointmentsCount = $this->appointments()
            ->whereDate('start_datetime', $date)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Check against attendants number (total capacity)
        return $appointmentsCount < $this->attendants_number;
    }
}
