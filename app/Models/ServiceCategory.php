<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    /**
     * Get the services that belong to this category.
     */
    public function services()
    {
        return $this->hasMany(Service::class, 'id_service_categories');
    }

    /**
     * Accessors & Mutators
     */

    /**
     * Get the number of services in this category.
     */
    public function getServicesCountAttribute(): int
    {
        return $this->services()->count();
    }

    /**
     * Get the number of active (public) services in this category.
     */
    public function getActiveServicesCountAttribute(): int
    {
        return $this->services()->where('is_private', false)->count();
    }

    /**
     * Check if category has any services.
     */
    public function getHasServicesAttribute(): bool
    {
        return $this->services()->exists();
    }

    /**
     * Get short description (first 100 characters).
     */
    public function getShortDescriptionAttribute(): string
    {
        if (!$this->description) {
            return '';
        }
        return strlen($this->description) > 100
            ? substr($this->description, 0, 100) . '...'
            : $this->description;
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to categories with services.
     */
    public function scopeWithServices($query)
    {
        return $query->whereHas('services');
    }

    /**
     * Scope a query to categories without services.
     */
    public function scopeWithoutServices($query)
    {
        return $query->whereDoesntHave('services');
    }

    /**
     * Scope a query to categories with public services.
     */
    public function scopeWithPublicServices($query)
    {
        return $query->whereHas('services', function ($q) {
            $q->where('is_private', false);
        });
    }

    /**
     * Scope a query to search categories by name or description.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope a query to order categories by service count.
     */
    public function scopeOrderByServicesCount($query, $direction = 'desc')
    {
        return $query->withCount('services')
            ->orderBy('services_count', $direction);
    }

    /**
     * Helper Methods
     */

    /**
     * Get all public services for this category.
     */
    public function getPublicServices()
    {
        return $this->services()
            ->where('is_private', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all active services with their providers.
     */
    public function getServicesWithProviders()
    {
        return $this->services()
            ->where('is_private', false)
            ->with('providers')
            ->get();
    }

    /**
     * Check if category can be deleted (has no services).
     */
    public function canBeDeleted(): bool
    {
        return !$this->has_services;
    }

    /**
     * Get average price of services in this category.
     */
    public function getAveragePriceAttribute(): ?float
    {
        return $this->services()
            ->whereNotNull('price')
            ->avg('price');
    }

    /**
     * Get price range of services in this category.
     */
    public function getPriceRangeAttribute(): array
    {
        $services = $this->services()->whereNotNull('price');

        return [
            'min' => $services->min('price'),
            'max' => $services->max('price'),
        ];
    }

    /**
     * Get the most popular service in this category (by appointment count).
     */
    public function getMostPopularService()
    {
        return $this->services()
            ->withCount('appointments')
            ->orderBy('appointments_count', 'desc')
            ->first();
    }

    /**
     * Get services grouped by price range.
     */
    public function getServicesByPriceRange(): array
    {
        $services = $this->services()->whereNotNull('price')->get();

        return [
            'budget' => $services->filter(fn($s) => $s->price < 50),
            'standard' => $services->filter(fn($s) => $s->price >= 50 && $s->price < 100),
            'premium' => $services->filter(fn($s) => $s->price >= 100),
        ];
    }

    /**
     * Get all unique providers offering services in this category.
     */
    public function getProviders()
    {
        return User::whereHas('services', function ($query) {
            $query->where('id_service_categories', $this->id);
        })->get();
    }

    /**
     * Check if category has services available for booking.
     */
    public function hasAvailableServices(): bool
    {
        return $this->services()
            ->where('is_private', false)
            ->whereHas('providers')
            ->exists();
    }
}
