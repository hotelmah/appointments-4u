<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Authentication
        'username',
        'email',
        'password',

        // Profile
        'first_name',
        'last_name',
        'mobile_phone_number',
        'work_phone_number',
        'address',
        'city',
        'state',
        'zip_code',
        'notes',

        // Preferences
        'timezone',
        'language',

        // Custom fields
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'custom_field_5',

        // Privacy & LDAP
        'is_private',
        'ldap_dn',

        // Role
        'id_roles',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_private' => 'boolean',
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Relationships
     */

    /**
     * Get the role that the user belongs to.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'id_roles');
    }

    /**
     * Get the user's settings.
     */
    public function settings()
    {
        return $this->hasOne(UserSetting::class, 'id_users');
    }

    /**
     * Get appointments where this user is the provider.
     */
    public function appointmentsAsProvider()
    {
        return $this->hasMany(Appointment::class, 'id_users_provider');
    }

    /**
     * Get appointments where this user is the customer.
     */
    public function appointmentsAsCustomer()
    {
        return $this->hasMany(Appointment::class, 'id_users_customer');
    }

    /**
     * Get all appointments for this user (as provider or customer).
     */
    public function appointments()
    {
        return Appointment::where('id_users_provider', $this->id)
            ->orWhere('id_users_customer', $this->id);
    }

    /**
     * Get the services that this user (provider) can offer.
     * Many-to-many relationship through services_providers pivot table.
     */
    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'services_providers',
            'id_users',
            'id_services'
        );
    }

    /**
     * Get the providers that this user (secretary) manages.
     * Many-to-many relationship through secretaries_providers pivot table.
     */
    public function managedProviders()
    {
        return $this->belongsToMany(
            User::class,
            'secretaries_providers',
            'id_users_secretary',
            'id_users_provider'
        );
    }

    /**
     * Get the secretaries that manage this user (provider).
     * Many-to-many relationship through secretaries_providers pivot table.
     */
    public function secretaries()
    {
        return $this->belongsToMany(
            User::class,
            'secretaries_providers',
            'id_users_provider',
            'id_users_secretary'
        );
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to only include providers.
     */
    public function scopeProviders($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'provider');
        });
    }

    /**
     * Scope a query to only include customers.
     */
    public function scopeCustomers($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'customer');
        });
    }

    /**
     * Scope a query to only include secretaries.
     */
    public function scopeSecretaries($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'secretary');
        });
    }

    /**
     * Scope a query to only include admins.
     */
    public function scopeAdmins($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('is_admin', true);
        });
    }

    /**
     * Scope a query to exclude private users.
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }
}
