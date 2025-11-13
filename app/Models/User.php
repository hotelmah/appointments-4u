<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

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
     * The attributes that should be hidden.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_private' => 'boolean',
        'id_roles' => 'integer',
    ];

    /**
     * Accessors
     */

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Relationships
     */

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_roles');
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class, 'id_users');
    }

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
     * Role-based Scopes (replaces separate models)
     */

    /**
     * Scope: Filter users by admin role.
     * Replaces Admins_model queries.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'admin');
        });
    }

    /**
     * Scope: Filter users by provider role.
     * Replaces Providers_model queries.
     */
    public function scopeProviders(Builder $query): Builder
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'provider');
        });
    }

    /**
     * Scope: Filter users by secretary role.
     * Replaces Secretaries_model queries.
     */
    public function scopeSecretaries(Builder $query): Builder
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'secretary');
        });
    }

    /**
     * Scope: Filter users by customer role.
     * Replaces Customers_model queries.
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'customer');
        });
    }

    /**
     * Scope: Exclude private users.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope: Search users by keyword.
     * Replaces search() method from CI3 models.
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('first_name', 'LIKE', "%{$keyword}%")
              ->orWhere('last_name', 'LIKE', "%{$keyword}%")
              ->orWhere('email', 'LIKE', "%{$keyword}%")
              ->orWhere('mobile_phone_number', 'LIKE', "%{$keyword}%")
              ->orWhere('work_phone_number', 'LIKE', "%{$keyword}%")
              ->orWhere('address', 'LIKE', "%{$keyword}%")
              ->orWhere('city', 'LIKE', "%{$keyword}%")
              ->orWhere('state', 'LIKE', "%{$keyword}%")
              ->orWhere('zip_code', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%");
        });
    }

    /**
     * Role Check Methods
     */

    public function isAdmin(): bool
    {
        return $this->role && $this->role->slug === 'admin';
    }

    public function isProvider(): bool
    {
        return $this->role && $this->role->slug === 'provider';
    }

    public function isSecretary(): bool
    {
        return $this->role && $this->role->slug === 'secretary';
    }

    public function isCustomer(): bool
    {
        return $this->role && $this->role->slug === 'customer';
    }

    /**
     * Static Helper Methods (CI3 compatibility)
     */

    /**
     * Get admin role ID.
     * Replaces Admins_model::get_admin_role_id()
     */
    public static function getAdminRoleId(): int
    {
        return Role::where('slug', 'admin')->value('id');
    }

    /**
     * Get provider role ID.
     */
    public static function getProviderRoleId(): int
    {
        return Role::where('slug', 'provider')->value('id');
    }

    /**
     * Get secretary role ID.
     */
    public static function getSecretaryRoleId(): int
    {
        return Role::where('slug', 'secretary')->value('id');
    }

    /**
     * Get customer role ID.
     */
    public static function getCustomerRoleId(): int
    {
        return Role::where('slug', 'customer')->value('id');
    }

    /**
     * Settings Management (replaces CI3 set_settings/get_settings)
     */

    /**
     * Get user settings with defaults.
     */
    // public function getSettingsAttribute()
    // {
    //     return $this->settings()->first() ?? new UserSetting([
    //         'id_users' => $this->id,
    //         'username' => '',
    //         'notifications' => true,
    //         'calendar_view' => 'default',
    //     ]);
    // }

    /**
     * Update user settings.
     */
    // public function updateSettings(array $settingsData): void
    // {
    //     if (!$this->settings) {
    //         $this->settings()->create(array_merge($settingsData, [
    //             'id_users' => $this->id
    //         ]));
    //     } else {
    //         $this->settings->update($settingsData);
    //     }
    // }

    /**
     * Get specific setting value.
     */
    // public function getSetting(string $name, $default = null)
    // {
    //     return $this->settings->{$name} ?? $default;
    // }

    /**
     * Set specific setting value.
     */
    // public function setSetting(string $name, $value): void
    // {
    //     if (!$this->settings) {
    //         $this->settings()->create([
    //             'id_users' => $this->id,
    //             $name => $value
    //         ]);
    //     } else {
    //         $this->settings->update([$name => $value]);
    //     }
    // }


    /**
     * Validation Methods
     */

    /**
     * Validate user data before saving.
     *
     * This method performs comprehensive validation including:
     * - Checking if user ID exists (for updates)
     * - Validating required fields
     * - Email format validation
     * - Email uniqueness check
     * - Username uniqueness check (if provided)
     * - Password validation
     * - Calendar view validation
     *
     * Migrated from CI3 Users_model::validate() and Admins_model::validate()
     *
     * @param array $userData Associative array with user data
     * @param string|null $roleSlug Optional role slug for role-specific validation
     * @throws \InvalidArgumentException
     */
    public static function validateUserData(array $userData, ?string $roleSlug = null): void
    {
        // 1. If a user ID is provided, check if the record exists
        if (!empty($userData['id'])) {
            $exists = static::where('id', $userData['id'])->exists();

            if (!$exists) {
                throw new \InvalidArgumentException(
                    "The provided user ID does not exist in the database: {$userData['id']}"
                );
            }
        }

        // 2. Validate required fields
        $requiredFields = [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'mobile_phone_number' => 'Mobile phone number',
        ];

        $missingFields = [];
        foreach ($requiredFields as $field => $label) {
            if (empty($userData[$field])) {
                $missingFields[] = $label;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                'Not all required fields are provided: ' . implode(', ', $missingFields) . '. ' .
                'Data received: ' . print_r($userData, true)
            );
        }

        // 3. Validate email format
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                "Invalid email address format: {$userData['email']}"
            );
        }

        // 4. Check email uniqueness
        $userId = $userData['id'] ?? null;
        $emailExists = static::where('email', $userData['email'])
            ->when($userId, function ($query) use ($userId) {
                return $query->where('id', '!=', $userId);
            })
            ->exists();

        if ($emailExists) {
            throw new \InvalidArgumentException(
                "The email address '{$userData['email']}' is already in use by another user."
            );
        }

        // 5. Validate settings (if provided)
        if (!empty($userData['settings'])) {
            static::validateUserSettings($userData['settings'], $userId);
        }
    }

    /**
     * Validate user settings data.
     *
     * Validates:
     * - Username uniqueness
     * - Password strength
     * - Calendar view value
     *
     * @param array $settings Settings data
     * @param int|null $userId User ID (for update operations)
     * @throws \InvalidArgumentException
     */
    public static function validateUserSettings(array $settings, ?int $userId = null): void
    {
        // 1. Validate username uniqueness (if provided)
        if (!empty($settings['username'])) {
            if (!static::isUsernameUnique($settings['username'], $userId)) {
                throw new \InvalidArgumentException(
                    "The username '{$settings['username']}' is already in use by another user."
                );
            }
        }

        // 2. Validate password strength (if provided)
        if (!empty($settings['password'])) {
            if (strlen($settings['password']) < 7) {
                throw new \InvalidArgumentException(
                    'The user password must be at least 7 characters long.'
                );
            }
        }

        // 3. For new users, password is required
        if (empty($userId) && empty($settings['password'])) {
            throw new \InvalidArgumentException(
                'Password is required when creating a new user.'
            );
        }

        // 4. Validate calendar view (if provided)
        if (!empty($settings['calendar_view'])) {
            $validCalendarViews = ['default', 'table'];
            if (!in_array($settings['calendar_view'], $validCalendarViews)) {
                throw new \InvalidArgumentException(
                    "Invalid calendar view value. Must be one of: " . implode(', ', $validCalendarViews)
                );
            }
        }
    }

    /**
     * Validate admin-specific data.
     *
     * This adds any admin-specific validation rules on top of base user validation.
     * Migrated from CI3 Admins_model::validate()
     *
     * @param array $adminData Admin data
     * @throws \InvalidArgumentException
     */
    public static function validateAdminData(array $adminData): void
    {
        // Call base user validation
        static::validateUserData($adminData, 'admin');

        // Admin-specific validation can be added here
        // For example, checking if deleting the last admin, etc.
    }

    /**
     * Validate provider-specific data.
     *
     * @param array $providerData Provider data
     * @throws \InvalidArgumentException
     */
    public static function validateProviderData(array $providerData): void
    {
        // Call base user validation
        static::validateUserData($providerData, 'provider');

        // Provider-specific validation
        // Example: Validate services relationship if provided
        if (!empty($providerData['services'])) {
            if (!is_array($providerData['services'])) {
                throw new \InvalidArgumentException(
                    'Provider services must be an array of service IDs.'
                );
            }
        }
    }

    /**
     * Validate secretary-specific data.
     *
     * @param array $secretaryData Secretary data
     * @throws \InvalidArgumentException
     */
    public static function validateSecretaryData(array $secretaryData): void
    {
        // Call base user validation
        static::validateUserData($secretaryData, 'secretary');

        // Secretary-specific validation
        // Example: Validate providers relationship if provided
        if (!empty($secretaryData['providers'])) {
            if (!is_array($secretaryData['providers'])) {
                throw new \InvalidArgumentException(
                    'Secretary providers must be an array of provider IDs.'
                );
            }
        }
    }

    /**
     * Validate customer-specific data.
     *
     * @param array $customerData Customer data
     * @throws \InvalidArgumentException
     */
    public static function validateCustomerData(array $customerData): void
    {
        // Call base user validation
        static::validateUserData($customerData, 'customer');

        // Customer-specific validation can be added here
    }

    /**
     * Validate before deletion.
     *
     * Prevents deletion if it would violate business rules.
     * Example: Cannot delete the last admin
     *
     * @param int $userId User ID to delete
     * @param string|null $roleSlug Role slug for role-specific checks
     * @throws \InvalidArgumentException
     */
    public static function validateBeforeDelete(int $userId, ?string $roleSlug = null): void
    {
        $user = static::findOrFail($userId);

        // If deleting an admin, check if it's the last admin
        if ($roleSlug === 'admin' || $user->isAdmin()) {
            $adminCount = static::admins()->count();

            if ($adminCount <= 1) {
                throw new \InvalidArgumentException(
                    'Cannot delete the last admin user. At least one admin must exist in the system.'
                );
            }
        }

        // Add more role-specific deletion validation here
        // Example: Check if provider has future appointments
        if ($roleSlug === 'provider' || $user->isProvider()) {
            $futureAppointments = $user->appointmentsAsProvider()
                ->where('start_datetime', '>', now())
                ->count();

            if ($futureAppointments > 0) {
                throw new \InvalidArgumentException(
                    "Cannot delete provider with {$futureAppointments} future appointments. Please reassign or cancel appointments first."
                );
            }
        }
    }

    /**
     * Check if email is unique (excluding specific user).
     *
     * @param string $email Email to check
     * @param int|null $excludeUserId User ID to exclude from check
     * @return bool
     */
    public static function isEmailUnique(string $email, ?int $excludeUserId = null): bool
    {
        $query = static::where('email', $email);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return !$query->exists();
    }

    /**
     * CRUD Helper Methods (CI3 Compatibility)
     *
     * These methods wrap Laravel's built-in save/create functionality
     * while maintaining CI3's business logic for user management.
     */

    /**
     * Save (insert or update) a user with validation and settings.
     *
     * This is a CI3 compatibility wrapper that:
     * 1. Validates user data
     * 2. Creates or updates the user record
     * 3. Handles password hashing
     * 4. Manages user settings
     *
     * Migrated from CI3 Users_model::save() and Admins_model::save()
     *
     * Note: This does NOT override Laravel's save() method.
     * Use User::saveUser($data) instead of $user->save()
     *
     * @param array $userData Associative array with user data
     * @param string|null $roleSlug Role slug for role-specific validation
     * @return int Returns the user ID
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public static function saveUser(array $userData, ?string $roleSlug = null): int
    {
        // Validate based on role
        switch ($roleSlug) {
            case 'admin':
                static::validateAdminData($userData);
                break;
            case 'provider':
                static::validateProviderData($userData);
                break;
            case 'secretary':
                static::validateSecretaryData($userData);
                break;
            case 'customer':
                static::validateCustomerData($userData);
                break;
            default:
                static::validateUserData($userData, $roleSlug);
        }

        // Determine if this is an insert or update
        if (empty($userData['id'])) {
            return static::insertUser($userData, $roleSlug);
        } else {
            return static::updateUser($userData, $roleSlug);
        }
    }

    /**
     * Insert a new user into the database.
     *
     * Handles:
     * - Setting create/update timestamps (automatic via Laravel)
     * - Password hashing
     * - Settings creation
     * - Role assignment
     *
     * Migrated from CI3 Users_model::insert() and Admins_model::insert()
     *
     * @param array $userData Associative array with user data
     * @param string|null $roleSlug Role slug for role assignment
     * @return int Returns the user ID
     * @throws \RuntimeException
     * @throws \Exception
     */
    protected static function insertUser(array $userData, ?string $roleSlug = null): int
    {
        try {
            // Extract settings before creating user
            $settings = $userData['settings'] ?? [];
            unset($userData['settings']);

            // Set role ID if role slug is provided
            if ($roleSlug && empty($userData['id_roles'])) {
                $userData['id_roles'] = match ($roleSlug) {
                    'admin' => static::getAdminRoleId(),
                    'provider' => static::getProviderRoleId(),
                    'secretary' => static::getSecretaryRoleId(),
                    'customer' => static::getCustomerRoleId(),
                    default => throw new \InvalidArgumentException("Invalid role slug: {$roleSlug}")
                };
            }

            // Hash password if provided
            if (!empty($settings['password'])) {
                $userData['password'] = Hash::make($settings['password']);
            }

            // Create the user (Laravel automatically sets created_at and updated_at)
            $user = static::create($userData);

            if (!$user) {
                throw new \RuntimeException('Could not insert user.');
            }

            // Create user settings
            if (!empty($settings)) {
                $user->updateSettings($settings);
            }

            return $user->id;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error inserting user: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
    * Update an existing user in the database.
    *
    * Handles:
    * - Updating user fields
    * - Password hashing (if changed)
    * - Settings update
    * - Update timestamp (automatic via Laravel)
    *
    * Note: In CI3, passwords were hashed with a salt stored in user_settings.
    * In Laravel, we use Hash::make() which includes bcrypt salt automatically.
    *
    * Migrated from CI3 Users_model::update() and Admins_model::update()
    *
    * @param array $userData Associative array with user data (must include 'id')
    * @param string|null $roleSlug Role slug for role-specific logic
    * @return int Returns the user ID
    * @throws \RuntimeException
    * @throws \Exception
    */
    protected static function updateUser(array $userData, ?string $roleSlug = null): int
    {
        try {
            $userId = $userData['id'];

            // Find the user
            $user = static::findOrFail($userId);

            // Extract settings before updating user
            $settings = $userData['settings'] ?? [];
            unset($userData['settings']);

            // Handle password update
            if (isset($settings['password'])) {
                // Check if settings record exists (CI3 compatibility check)
                if (!$user->settings) {
                    throw new \RuntimeException(
                        "No settings record found for user with ID: {$userId}"
                    );
                }

                // Hash the new password (Laravel way - no salt needed)
                // CI3 used: hash_password($existing_settings['salt'], $settings['password'])
                // Laravel uses: Hash::make($password) which includes salt automatically
                $userData['password'] = Hash::make($settings['password']);

                // Don't include password in settings update
                unset($settings['password']);
            }

            // Remove ID from update data (Laravel doesn't allow updating primary key)
            unset($userData['id']);

            // Update user fields (Laravel automatically updates updated_at)
            // CI3 set: $user['update_datetime'] = date('Y-m-d H:i:s');
            // Laravel handles this automatically via timestamps
            if (!$user->update($userData)) {
                throw new \RuntimeException('Could not update user.');
            }

            // Update settings if provided
            if (!empty($settings)) {
                static::setSettings($userId, $settings);
            }

            return $user->id;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error updating user: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
    * Delete a user from the database.
    *
    * This method:
    * 1. Validates before deletion (prevents deleting last admin, etc.)
    * 2. Deletes the user record
    * 3. Cascade deletes related records (settings, appointments, etc.)
    *
    * Migrated from CI3 Users_model::delete() and Admins_model::delete()
    *
    * @param int $userId User ID to delete
    * @param string|null $roleSlug Role slug for role-specific checks
    * @throws \RuntimeException
    * @throws \InvalidArgumentException
    */
    public static function deleteUser(int $userId, ?string $roleSlug = null): void
    {
        try {
            // Validate before deletion (business logic checks)
            static::validateBeforeDelete($userId, $roleSlug);

            // Find the user
            $user = static::findOrFail($userId);

            // Delete the user
            // Note: Related records (settings, appointments, etc.) should be handled by:
            // 1. Database foreign key constraints with ON DELETE CASCADE, or
            // 2. Model events (deleting/deleted), or
            // 3. Manual cleanup here

            if (!$user->delete()) {
                throw new \RuntimeException("Could not delete user with ID: {$userId}");
            }

            // Optional: Manual cleanup if not using cascade deletes
            // This ensures settings are deleted even without foreign key constraints
            if ($user->settings) {
                $user->settings->delete();
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \RuntimeException("User with ID {$userId} not found.", 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException('Error deleting user: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
    * Delete multiple users.
    *
    * Convenience method for batch deletion with validation.
    *
    * @param array $userIds Array of user IDs to delete
    * @param string|null $roleSlug Role slug for role-specific checks
    * @return array Returns array with success/failure for each user
    */
    public static function deleteUsers(array $userIds, ?string $roleSlug = null): array
    {
        $results = [];

        foreach ($userIds as $userId) {
            try {
                static::deleteUser($userId, $roleSlug);
                $results[$userId] = ['success' => true];
            } catch (\Exception $e) {
                $results[$userId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
    * Soft delete a user (if soft deletes are enabled).
    *
    * Note: To enable soft deletes, add SoftDeletes trait to User model
    * and add 'deleted_at' column to users table.
    *
    * @param int $userId User ID to soft delete
    * @return bool
    */
    public static function softDeleteUser(int $userId): bool
    {
        $user = static::findOrFail($userId);
        return $user->delete(); // Will soft delete if SoftDeletes trait is used
    }

    /**
    * Restore a soft-deleted user.
    *
    * @param int $userId User ID to restore
    * @return bool
    */
    public static function restoreUser(int $userId): bool
    {
        $user = static::withTrashed()->findOrFail($userId);
        return $user->restore();
    }

    /**
    * Force delete a user (permanent deletion even if soft deletes enabled).
    *
    * @param int $userId User ID to force delete
    * @return bool
    */
    public static function forceDeleteUser(int $userId): bool
    {
        $user = static::withTrashed()->findOrFail($userId);
        return $user->forceDelete();
    }


    /**
     * Find a user by ID with settings loaded.
     *
     * This is a convenience method that always loads settings.
     * Migrated from CI3 Users_model::find() and Admins_model::find()
     *
     * @param int $userId User ID
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findWithSettings(int $userId): User
    {
        $user = static::with(['role', 'settings'])->findOrFail($userId);
        return $user;
    }

    /**
     * Find a user by ID with settings and data casting (CI3 compatibility).
     *
     * This method:
     * 1. Finds the user by ID
     * 2. Loads settings
     * 3. Returns as array (CI3 style) or model instance (Laravel style)
     *
     * Migrated from CI3 Users_model::find() and Admins_model::find()
     *
     * @param int $userId User ID
     * @param bool $asArray Return as array (CI3 style) or model (Laravel style)
     * @return array|User
     * @throws \InvalidArgumentException
     */
    public static function findUser(int $userId, bool $asArray = false): array|User
    {
        try {
            // Find the user with relationships
            $user = static::with(['role', 'settings'])->find($userId);

            if (!$user) {
                throw new \InvalidArgumentException(
                    "The provided user ID was not found in the database: {$userId}"
                );
            }

            // If returning as array (CI3 compatibility)
            if ($asArray) {
                $userData = $user->toArray();

                // Cast data types (CI3 compatibility)
                static::castUserData($userData);

                // Add settings as separate key (CI3 format)
                $userData['settings'] = static::getSettings($userId);

                return $userData;
            }

            // Return as model instance (Laravel style)
            return $user;
        } catch (\Exception $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw $e;
            }
            throw new \InvalidArgumentException(
                "Error finding user with ID {$userId}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Find an admin by ID (CI3 compatibility).
     *
     * Wrapper specifically for admin users.
     *
     * @param int $adminId Admin ID
     * @param bool $asArray Return as array or model
     * @return array|User
     * @throws \InvalidArgumentException
     */
    public static function findAdmin(int $adminId, bool $asArray = false): array|User
    {
        $user = static::findUser($adminId, $asArray);

        // Verify it's actually an admin
        if (is_array($user)) {
            $roleId = $user['id_roles'];
        } else {
            $roleId = $user->id_roles;
        }

        if ($roleId !== static::getAdminRoleId()) {
            throw new \InvalidArgumentException(
                "User with ID {$adminId} is not an admin."
            );
        }

        return $user;
    }

    /**
     * Find a provider by ID (CI3 compatibility).
     *
     * @param int $providerId Provider ID
     * @param bool $asArray Return as array or model
     * @return array|User
     * @throws \InvalidArgumentException
     */
    public static function findProvider(int $providerId, bool $asArray = false): array|User
    {
        $user = static::findUser($providerId, $asArray);

        // Verify it's actually a provider
        if (is_array($user)) {
            $roleId = $user['id_roles'];
        } else {
            $roleId = $user->id_roles;
        }

        if ($roleId !== static::getProviderRoleId()) {
            throw new \InvalidArgumentException(
                "User with ID {$providerId} is not a provider."
            );
        }

        return $user;
    }

    /**
     * Find a secretary by ID (CI3 compatibility).
     *
     * @param int $secretaryId Secretary ID
     * @param bool $asArray Return as array or model
     * @return array|User
     * @throws \InvalidArgumentException
     */
    public static function findSecretary(int $secretaryId, bool $asArray = false): array|User
    {
        $user = static::findUser($secretaryId, $asArray);

        // Verify it's actually a secretary
        if (is_array($user)) {
            $roleId = $user['id_roles'];
        } else {
            $roleId = $user->id_roles;
        }

        if ($roleId !== static::getSecretaryRoleId()) {
            throw new \InvalidArgumentException(
                "User with ID {$secretaryId} is not a secretary."
            );
        }

        return $user;
    }

    /**
     * Find a customer by ID (CI3 compatibility).
     *
     * @param int $customerId Customer ID
     * @param bool $asArray Return as array or model
     * @return array|User
     * @throws \InvalidArgumentException
     */
    public static function findCustomer(int $customerId, bool $asArray = false): array|User
    {
        $user = static::findUser($customerId, $asArray);

        // Verify it's actually a customer
        if (is_array($user)) {
            $roleId = $user['id_roles'];
        } else {
            $roleId = $user->id_roles;
        }

        if ($roleId !== static::getCustomerRoleId()) {
            throw new \InvalidArgumentException(
                "User with ID {$customerId} is not a customer."
            );
        }

        return $user;
    }

    /**
     * Cast user data to appropriate types (CI3 compatibility).
     *
     * CI3 models have a $casts property that defines type casting.
     * This method applies those casts to maintain compatibility.
     *
     * @param array &$userData User data array (modified in place)
     */
    protected static function castUserData(array &$userData): void
    {
        // Define casts (same as CI3 Users_model and Admins_model)
        $casts = [
            'id' => 'integer',
            'id_roles' => 'integer',
            'is_private' => 'boolean',
        ];

        foreach ($casts as $field => $type) {
            if (!isset($userData[$field])) {
                continue;
            }

            match ($type) {
                'integer' => $userData[$field] = (int) $userData[$field],
                'boolean' => $userData[$field] = (bool) $userData[$field],
                'float' => $userData[$field] = (float) $userData[$field],
                'string' => $userData[$field] = (string) $userData[$field],
                'array' => $userData[$field] = is_array($userData[$field])
                    ? $userData[$field]
                    : json_decode($userData[$field], true),
                default => null,
            };
        }
    }

    /**
    * Get a specific field value from a user (enhanced with CI3 validation).
    *
    * This method:
    * 1. Validates the field and user ID parameters
    * 2. Checks if the user exists
    * 3. Casts the data to proper types
    * 4. Checks if the field exists in user or settings
    * 5. Returns the field value
    *
    * Migrated from CI3 Users_model::value() and Admins_model::value()
    *
    * @param int $userId User ID
    * @param string $field Field name
    * @return mixed Field value
    * @throws \InvalidArgumentException
    */
    public static function value(int $userId, string $field): mixed
    {
        // 1. Validate the field parameter
        if (empty($field)) {
            throw new \InvalidArgumentException('The field argument cannot be empty.');
        }

        // 2. Validate the user ID parameter
        if (empty($userId)) {
            throw new \InvalidArgumentException('The user ID argument cannot be empty.');
        }

        // 3. Check whether the user exists
        $user = static::with(['role', 'settings'])->find($userId);

        if (!$user) {
            throw new \InvalidArgumentException(
                "The provided user ID was not found in the database: {$userId}"
            );
        }

        // 4. Get user data as array and cast it (CI3 compatibility)
        $userData = $user->toArray();
        static::castUserData($userData);

        // 5. Check if the field exists in user data
        if (array_key_exists($field, $userData)) {
            return $userData[$field];
        }

        // 6. Check if the field exists in settings
        if ($user->settings && array_key_exists($field, $user->settings->toArray())) {
            return $user->settings->{$field};
        }

        // 7. Field not found - throw exception
        throw new \InvalidArgumentException(
            "The requested field was not found in the user data: {$field}"
        );
    }


    /**
    * Get a specific admin field value (role-specific wrapper).
    *
    * Wrapper specifically for admin users with role verification.
    *
    * @param int $adminId Admin ID
    * @param string $field Field name
    * @return mixed Field value
    * @throws \InvalidArgumentException
    */
    public static function adminValue(int $adminId, string $field): mixed
    {
        // Verify the user is an admin
        $user = static::findOrFail($adminId);

        if (!$user->isAdmin()) {
            throw new \InvalidArgumentException(
                "User with ID {$adminId} is not an admin."
            );
        }

        return static::value($adminId, $field);
    }

    /**
    * Get a specific provider field value (role-specific wrapper).
    *
    * @param int $providerId Provider ID
    * @param string $field Field name
    * @return mixed Field value
    * @throws \InvalidArgumentException
    */
    public static function providerValue(int $providerId, string $field): mixed
    {
        $user = static::findOrFail($providerId);

        if (!$user->isProvider()) {
            throw new \InvalidArgumentException(
                "User with ID {$providerId} is not a provider."
            );
        }

        return static::value($providerId, $field);
    }

    /**
    * Get a specific secretary field value (role-specific wrapper).
    *
    * @param int $secretaryId Secretary ID
    * @param string $field Field name
    * @return mixed Field value
    * @throws \InvalidArgumentException
    */
    public static function secretaryValue(int $secretaryId, string $field): mixed
    {
        $user = static::findOrFail($secretaryId);

        if (!$user->isSecretary()) {
            throw new \InvalidArgumentException(
                "User with ID {$secretaryId} is not a secretary."
            );
        }

        return static::value($secretaryId, $field);
    }

    /**
    * Get a specific customer field value (role-specific wrapper).
    *
    * @param int $customerId Customer ID
    * @param string $field Field name
    * @return mixed Field value
    * @throws \InvalidArgumentException
    */
    public static function customerValue(int $customerId, string $field): mixed
    {
        $user = static::findOrFail($customerId);

        if (!$user->isCustomer()) {
            throw new \InvalidArgumentException(
                "User with ID {$customerId} is not a customer."
            );
        }

        return static::value($customerId, $field);
    }

    /**
    * Get multiple field values for a user.
    *
    * Convenience method to get multiple fields at once.
    *
    * @param int $userId User ID
    * @param array $fields Array of field names
    * @return array Associative array of field => value
    * @throws \InvalidArgumentException
    */
    public static function values(int $userId, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            try {
                $result[$field] = static::value($userId, $field);
            } catch (\InvalidArgumentException $e) {
                // If field doesn't exist, include error in result
                $result[$field] = null;
            }
        }

        return $result;
    }

    /**
    * Check if a field exists for a user.
    *
    * @param int $userId User ID
    * @param string $field Field name
    * @return bool True if field exists
    */
    public static function hasField(int $userId, string $field): bool
    {
        try {
            static::value($userId, $field);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
    * Get all available fields for a user.
    *
    * Returns a list of all field names available for the user.
    *
    * @param int $userId User ID
    * @return array Array of field names
    * @throws \InvalidArgumentException
    */
    public static function getAvailableFields(int $userId): array
    {
        $user = static::with('settings')->findOrFail($userId);

        if (!$user) {
            throw new \InvalidArgumentException(
                "The provided user ID was not found in the database: {$userId}"
            );
        }

        $userFields = array_keys($user->toArray());
        $settingsFields = $user->settings ? array_keys($user->settings->toArray()) : [];

        // Remove redundant fields
        $settingsFields = array_diff($settingsFields, ['id_users']);

        return array_merge($userFields, $settingsFields);
    }


    /**
     * Find multiple users by IDs.
     *
     * Convenience method for batch retrieval.
     *
     * @param array $userIds Array of user IDs
     * @param bool $withSettings Load settings relationship
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findMany(array $userIds, bool $withSettings = true): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::whereIn('id', $userIds);

        if ($withSettings) {
            $query->with(['role', 'settings']);
        }

        return $query->get();
    }

    /**
     * Find users by role.
     *
     * Convenience method to get all users of a specific role.
     *
     * @param string $roleSlug Role slug (admin, provider, secretary, customer)
     * @param bool $withSettings Load settings relationship
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findByRole(string $roleSlug, bool $withSettings = true): \Illuminate\Database\Eloquent\Collection
    {
        $query = match ($roleSlug) {
            'admin' => static::admins(),
            'provider' => static::providers(),
            'secretary' => static::secretaries(),
            'customer' => static::customers(),
            default => throw new \InvalidArgumentException("Invalid role slug: {$roleSlug}")
        };

        if ($withSettings) {
            $query->with(['role', 'settings']);
        }

        return $query->get();
    }

    /**
     * Check if a user exists.
     *
     * @param int $userId User ID
     * @return bool
     */
    public static function exists(int $userId): bool
    {
        return static::where('id', $userId)->exists();
    }

    /**
     * Get user with all relationships loaded (for detailed view).
     *
     * @param int $userId User ID
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findWithAllRelations(int $userId): User
    {
        return static::with([
            'role',
            'settings',
            'appointmentsAsProvider',
            'appointmentsAsCustomer',
            'services',
            'managedProviders',
            'secretaries',
        ])->findOrFail($userId);
    }

    /**
     * Save admin user (CI3 compatibility).
     *
     * Wrapper specifically for admin users.
     *
     * @param array $adminData Admin data
     * @return int Admin user ID
     */
    public static function saveAdmin(array $adminData): int
    {
        return static::saveUser($adminData, 'admin');
    }

    /**
     * Save provider user (CI3 compatibility).
     *
     * @param array $providerData Provider data
     * @return int Provider user ID
     */
    public static function saveProvider(array $providerData): int
    {
        $userId = static::saveUser($providerData, 'provider');

        // Handle services relationship if provided
        if (!empty($providerData['services'])) {
            $user = static::find($userId);
            $user->services()->sync($providerData['services']);
        }

        return $userId;
    }

    /**
     * Save secretary user (CI3 compatibility).
     *
     * @param array $secretaryData Secretary data
     * @return int Secretary user ID
     */
    public static function saveSecretary(array $secretaryData): int
    {
        $userId = static::saveUser($secretaryData, 'secretary');

        // Handle managed providers relationship if provided
        if (!empty($secretaryData['providers'])) {
            $user = static::find($userId);
            $user->managedProviders()->sync($secretaryData['providers']);
        }

        return $userId;
    }

    /**
     * Save customer user (CI3 compatibility).
     *
     * @param array $customerData Customer data
     * @return int Customer user ID
     */
    public static function saveCustomer(array $customerData): int
    {
        return static::saveUser($customerData, 'customer');
    }

    /**
     * Settings Management (CI3 Compatibility)
     *
     * These methods manage user settings in the user_settings table.
     * Migrated from CI3 Users_model::set_settings(), get_settings(), set_setting()
     */

    /**
     * Save the user settings (create or update all settings at once).
     *
     * This method:
     * 1. Creates a settings record if it doesn't exist
     * 2. Updates each setting field individually
     *
     * Migrated from CI3 Users_model::set_settings() and Admins_model::set_settings()
     *
     * @param int $userId User ID
     * @param array $settings Associative array with the settings data
     * @throws \InvalidArgumentException
     */
    public static function setSettings(int $userId, array $settings): void
    {
        if (empty($settings)) {
            throw new \InvalidArgumentException('The settings argument cannot be empty.');
        }

        $user = static::findOrFail($userId);

        // Ensure a settings record exists
        if (!$user->settings) {
            UserSetting::create(['id_users' => $userId]);
            $user->load('settings'); // Reload the relationship
        }

        // Update each setting individually
        foreach ($settings as $name => $value) {
            static::setSetting($userId, $name, $value);
        }
    }

    /**
     * Get all user settings (excluding sensitive fields).
     *
     * Returns all settings except:
     * - id_users (redundant)
     * - password (security)
     * - salt (deprecated, not used in Laravel)
     *
     * Migrated from CI3 Users_model::get_settings() and Admins_model::get_settings()
     *
     * @param int $userId User ID
     * @return array Settings array
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getSettings(int $userId): array
    {
        $user = static::findOrFail($userId);

        if (!$user->settings) {
            // Return empty settings if none exist
            return [];
        }

        // Get settings as array
        $settings = $user->settings->toArray();

        // Remove sensitive/redundant fields (CI3 compatibility)
        unset(
            $settings['id_users'],
            $settings['password'],  // Not in current schema, but for CI3 compatibility
            $settings['salt']       // Not in current schema, but for CI3 compatibility
        );

        return $settings;
    }

    /**
     * Set a single user setting value.
     *
     * Updates a specific setting field for the user.
     * Creates the settings record if it doesn't exist.
     *
     * Migrated from CI3 Users_model::set_setting() and Admins_model::set_setting()
     *
     * @param int $userId User ID
     * @param string $name Setting name (column name in user_settings table)
     * @param mixed $value Setting value
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public static function setSetting(int $userId, string $name, mixed $value): void
    {
        $user = static::findOrFail($userId);

        // Ensure a settings record exists
        if (!$user->settings) {
            UserSetting::create(['id_users' => $userId]);
            $user->load('settings');
        }

        // Validate that the field exists in the model's fillable array
        if (!in_array($name, (new UserSetting())->getFillable())) {
            throw new \InvalidArgumentException(
                "Invalid setting name: {$name}. This field is not fillable in user_settings."
            );
        }

        // Update the specific setting
        $updated = $user->settings->update([$name => $value]);

        if (!$updated) {
            throw new \RuntimeException(
                "Could not set the new user setting value: {$name}"
            );
        }

        // Reload the relationship to reflect the change
        $user->load('settings');
    }

    // Keep the existing flexible Laravel-style getSetting for convenience
    /**
    * Get a user setting value (Laravel-style, flexible).
    *
    * This is the flexible Laravel version that:
    * - Returns mixed type (not just string)
    * - Returns null or default value if not found (no exception)
    * - More convenient for Laravel code
    *
    * Note: Already exists in the previous migration
    *
    * @param int $userId User ID
    * @param string $name Setting name
    * @return mixed Setting value or null
    */
    public static function getSetting(int $userId, string $name): mixed
    {
        try {
            $user = static::findOrFail($userId);

            if (!$user->settings) {
                return null;
            }

            return $user->settings->{$name} ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }



    /**
    * Get multiple user setting values at once.
    *
    * Convenience method for batch retrieval.
    *
    * @param int $userId User ID
    * @param array $names Array of setting names
    * @param bool $strict If true, throws exception on missing values (CI3 style)
    * @return array Associative array of name => value
    * @throws \RuntimeException If strict mode and any value is missing
    */
    public static function getUserSettings(int $userId, array $names, bool $strict = false): array
    {
        $result = [];

        foreach ($names as $name) {
            try {
                if ($strict) {
                    // CI3-style: throw exception if not found
                    $result[$name] = static::getUserSetting($userId, $name);
                } else {
                    // Laravel-style: return null if not found
                    $result[$name] = static::getSetting($userId, $name);
                }
            } catch (\RuntimeException $e) {
                if ($strict) {
                    throw $e;
                }
                $result[$name] = null;
            }
        }

        return $result;
    }

    /**
    * Check if a user setting exists and has a non-empty value.
    *
    * @param int $userId User ID
    * @param string $name Setting name
    * @return bool True if setting exists and is not empty
    */
    public static function hasUserSetting(int $userId, string $name): bool
    {
        try {
            static::getUserSetting($userId, $name);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }


    /**
     * Instance method: Get user settings with defaults (already exists, enhanced).
     *
     * This returns settings with automatic defaults if settings don't exist.
     * Useful when you have a user instance and want to access settings.
     *
     * @return UserSetting
     */
    public function getSettingsAttribute()
    {
        return $this->settings()->first() ?? new UserSetting([
            'id_users' => $this->id,
            'notifications' => true,
            'calendar_view' => 'default',
            'google_sync' => false,
            'caldav_sync' => false,
            'sync_past_days' => 30,
            'sync_future_days' => 90,
        ]);
    }





    /**
    * Get a specific user setting value (CI3 strict compatibility).
    *
    * This is the strict CI3 version that:
    * 1. Returns only string type
    * 2. Throws RuntimeException if setting is empty/not found
    * 3. Matches CI3's exact behavior
    *
    * Migrated from CI3 Users_model::get_setting() and Admins_model::get_setting()
    *
    * @param int $userId User ID
    * @param string $name Setting name
    * @return string Setting value (always returns string)
    * @throws \RuntimeException If setting value is not found or empty
    */
    public static function getUserSetting(int $userId, string $name): string
    {
        // Get the user settings record
        $settings = UserSetting::where('id_users', $userId)->first();

        if (!$settings) {
            throw new \RuntimeException(
                "The requested setting value was not found: {$userId}"
            );
        }

        // Get the specific setting value
        $value = $settings->{$name} ?? null;

        // Check if value is empty (CI3 compatibility)
        if (empty($value)) {
            throw new \RuntimeException(
                "The requested setting value was not found: {$userId}"
            );
        }

        // Return as string (CI3 returns string type)
        return (string) $value;
    }

    /**
    * Get a specific admin setting value (role-specific wrapper).
    *
    * Strict CI3 compatibility with role verification.
    *
    * @param int $adminId Admin ID
    * @param string $name Setting name
    * @return string Setting value
    * @throws \RuntimeException
    * @throws \InvalidArgumentException
    */
    public static function getAdminSetting(int $adminId, string $name): string
    {
        // Verify the user is an admin
        $user = static::findOrFail($adminId);

        if (!$user->isAdmin()) {
            throw new \InvalidArgumentException(
                "User with ID {$adminId} is not an admin."
            );
        }

        return static::getUserSetting($adminId, $name);
    }

    /**
    * Get a specific provider setting value (role-specific wrapper).
    *
    * @param int $providerId Provider ID
    * @param string $name Setting name
    * @return string Setting value
    * @throws \RuntimeException
    * @throws \InvalidArgumentException
    */
    public static function getProviderSetting(int $providerId, string $name): string
    {
        $user = static::findOrFail($providerId);

        if (!$user->isProvider()) {
            throw new \InvalidArgumentException(
                "User with ID {$providerId} is not a provider."
            );
        }

        return static::getUserSetting($providerId, $name);
    }

    /**
    * Get a specific secretary setting value (role-specific wrapper).
    *
    * @param int $secretaryId Secretary ID
    * @param string $name Setting name
    * @return string Setting value
    * @throws \RuntimeException
    * @throws \InvalidArgumentException
    */
    public static function getSecretarySetting(int $secretaryId, string $name): string
    {
        $user = static::findOrFail($secretaryId);

        if (!$user->isSecretary()) {
            throw new \InvalidArgumentException(
                "User with ID {$secretaryId} is not a secretary."
            );
        }

        return static::getUserSetting($secretaryId, $name);
    }

    /**
    * Get a specific customer setting value (role-specific wrapper).
    *
    * @param int $customerId Customer ID
    * @param string $name Setting name
    * @return string Setting value
    * @throws \RuntimeException
    * @throws \InvalidArgumentException
    */
    public static function getCustomerSetting(int $customerId, string $name): string
    {
        $user = static::findOrFail($customerId);

        if (!$user->isCustomer()) {
            throw new \InvalidArgumentException(
                "User with ID {$customerId} is not a customer."
            );
        }

        return static::getUserSetting($customerId, $name);
    }














    /**
     * Instance method: Update user settings (enhanced version).
     *
     * This method is more Laravel-style and creates/updates settings in one call.
     *
     * @param array $settingsData Settings data to update
     */
    public function updateSettings(array $settingsData): void
    {
        if (!$this->settings()->exists()) {
            $this->settings()->create(array_merge($settingsData, [
                'id_users' => $this->id
            ]));
        } else {
            $this->settings->update($settingsData);
        }

        // Reload relationship
        $this->load('settings');
    }

    /**
     * Check if user has settings record.
     *
     * @return bool
     */
    public function hasSettings(): bool
    {
        return $this->settings()->exists();
    }

    /**
     * Get settings with all defaults populated.
     *
     * Returns settings with default values for any missing fields.
     *
     * @return array
     */
    public function getSettingsWithDefaults(): array
    {
        $settings = $this->hasSettings()
            ? $this->settings->toArray()
            : [];

        // Merge with defaults
        $defaults = [
            'notifications' => true,
            'calendar_view' => 'default',
            'google_sync' => false,
            'caldav_sync' => false,
            'sync_past_days' => 30,
            'sync_future_days' => 90,
            'working_plan' => UserSetting::DEFAULT_WORKING_PLAN,
            'working_plan_exceptions' => [],
        ];

        return array_merge($defaults, $settings);
    }

    /**
     * Delete user settings.
     *
     * Removes the settings record for this user.
     *
     * @return bool
     */
    public function deleteSettings(): bool
    {
        if ($this->settings) {
            return $this->settings->delete();
        }

        return true;
    }



    /**
    * Search Methods (CI3 Compatibility)
    *
    * These methods handle searching users by keyword across multiple fields.
    * Migrated from CI3 Users_model::search() and Admins_model::search()
    */

    /**
    * Search users by keyword (CI3 compatibility).
    *
    * Searches across multiple fields:
    * - first_name, last_name
    * - email
    * - mobile_phone_number, work_phone_number
    * - address, city, state, zip_code
    * - notes
    *
    * Returns users with settings loaded and data cast to proper types.
    *
    * Migrated from CI3 Users_model::search() and Admins_model::search()
    *
    * @param string $keyword Search keyword
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by (e.g., "first_name ASC" or "updated_at DESC")
    * @param bool $asArray Return as array (CI3 style) or collection (Laravel style)
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function searchUsers(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        // Start the query
        $query = static::with(['role', 'settings']);

        // Apply keyword search across multiple fields (CI3 compatibility)
        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('mobile_phone_number', 'like', "%{$keyword}%")
                ->orWhere('work_phone_number', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")
                ->orWhere('city', 'like', "%{$keyword}%")
                ->orWhere('state', 'like', "%{$keyword}%")
                ->orWhere('zip_code', 'like', "%{$keyword}%")
                ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        // Apply ordering (CI3 format: "column_name ASC" or "column_name DESC")
        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            // Validate direction
            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        // Apply limit and offset
        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        // Get results
        $users = $query->get();

        // If returning as array (CI3 compatibility)
        if ($asArray) {
            $usersArray = [];

            foreach ($users as $user) {
                $userData = $user->toArray();

                // Cast data types (CI3 compatibility)
                static::castUserData($userData);

                // Add settings as separate key (CI3 format)
                $userData['settings'] = static::getSettings($user->id);

                $usersArray[] = $userData;
            }

            return $usersArray;
        }

        // Return as Laravel collection
        return $users;
    }

    /**
    * Search admins by keyword (role-specific wrapper).
    *
    * @param string $keyword Search keyword
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function searchAdmins(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        $query = static::admins()->with(['role', 'settings']);

        // Apply keyword search
        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('mobile_phone_number', 'like', "%{$keyword}%")
                ->orWhere('work_phone_number', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")
                ->orWhere('city', 'like', "%{$keyword}%")
                ->orWhere('state', 'like', "%{$keyword}%")
                ->orWhere('zip_code', 'like', "%{$keyword}%")
                ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        // Apply ordering
        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        // Apply limit and offset
        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        // Get results
        $admins = $query->get();

        // If returning as array
        if ($asArray) {
            $adminsArray = [];

            foreach ($admins as $admin) {
                $adminData = $admin->toArray();
                static::castUserData($adminData);
                $adminData['settings'] = static::getSettings($admin->id);
                $adminsArray[] = $adminData;
            }

            return $adminsArray;
        }

        return $admins;
    }

    /**
    * Search providers by keyword (role-specific wrapper).
    *
    * @param string $keyword Search keyword
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function searchProviders(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        $query = static::providers()->with(['role', 'settings', 'services']);

        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('mobile_phone_number', 'like', "%{$keyword}%")
                ->orWhere('work_phone_number', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")
                ->orWhere('city', 'like', "%{$keyword}%")
                ->orWhere('state', 'like', "%{$keyword}%")
                ->orWhere('zip_code', 'like', "%{$keyword}%")
                ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        $providers = $query->get();

        if ($asArray) {
            $providersArray = [];

            foreach ($providers as $provider) {
                $providerData = $provider->toArray();
                static::castUserData($providerData);
                $providerData['settings'] = static::getSettings($provider->id);
                $providersArray[] = $providerData;
            }

            return $providersArray;
        }

        return $providers;
    }

    /**
    * Search secretaries by keyword (role-specific wrapper).
    *
    * @param string $keyword Search keyword
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function searchSecretaries(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        $query = static::secretaries()->with(['role', 'settings', 'managedProviders']);

        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('mobile_phone_number', 'like', "%{$keyword}%")
                ->orWhere('work_phone_number', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")
                ->orWhere('city', 'like', "%{$keyword}%")
                ->orWhere('state', 'like', "%{$keyword}%")
                ->orWhere('zip_code', 'like', "%{$keyword}%")
                ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        $secretaries = $query->get();

        if ($asArray) {
            $secretariesArray = [];

            foreach ($secretaries as $secretary) {
                $secretaryData = $secretary->toArray();
                static::castUserData($secretaryData);
                $secretaryData['settings'] = static::getSettings($secretary->id);
                $secretariesArray[] = $secretaryData;
            }

            return $secretariesArray;
        }

        return $secretaries;
    }

    /**
    * Search customers by keyword (role-specific wrapper).
    *
    * @param string $keyword Search keyword
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function searchCustomers(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        $query = static::customers()->with(['role', 'settings']);

        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('mobile_phone_number', 'like', "%{$keyword}%")
                ->orWhere('work_phone_number', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")
                ->orWhere('city', 'like', "%{$keyword}%")
                ->orWhere('state', 'like', "%{$keyword}%")
                ->orWhere('zip_code', 'like', "%{$keyword}%")
                ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        $customers = $query->get();

        if ($asArray) {
            $customersArray = [];

            foreach ($customers as $customer) {
                $customerData = $customer->toArray();
                static::castUserData($customerData);
                $customerData['settings'] = static::getSettings($customer->id);
                $customersArray[] = $customerData;
            }

            return $customersArray;
        }

        return $customers;
    }

    /**
    * Count search results (useful for pagination).
    *
    * @param string $keyword Search keyword
    * @param string|null $roleSlug Filter by role (optional)
    * @return int Total count of matching records
    */
    public static function searchCount(string $keyword, ?string $roleSlug = null): int
    {
        $query = static::query();

        // Filter by role if provided
        if ($roleSlug) {
            $query->where('id_roles', match ($roleSlug) {
                'admin' => static::getAdminRoleId(),
                'provider' => static::getProviderRoleId(),
                'secretary' => static::getSecretaryRoleId(),
                'customer' => static::getCustomerRoleId(),
                default => throw new \InvalidArgumentException("Invalid role slug: {$roleSlug}")
            });
        }

        // Apply keyword search
        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('mobile_phone_number', 'like', "%{$keyword}%")
                ->orWhere('work_phone_number', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")
                ->orWhere('city', 'like', "%{$keyword}%")
                ->orWhere('state', 'like', "%{$keyword}%")
                ->orWhere('zip_code', 'like', "%{$keyword}%")
                ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        return $query->count();
    }


    /**
    * Get Methods (CI3 Compatibility - Flexible Query)
    *
    * These methods handle getting users with flexible WHERE conditions.
    * Migrated from CI3 Users_model::get() and Admins_model::get()
    */

    /**
    * Get all users that match the provided criteria (CI3 compatibility).
    *
    * This is a flexible query method that accepts:
    * - WHERE conditions (array or string)
    * - Limit and offset for pagination
    * - Order by clause
    *
    * Returns users with settings loaded and data cast to proper types.
    *
    * Migrated from CI3 Users_model::get() and Admins_model::get()
    *
    * @param array|string|null $where Where conditions (array or raw string)
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by (e.g., "first_name ASC" or "updated_at DESC")
    * @param bool $asArray Return as array (CI3 style) or collection (Laravel style)
    * @return array|\Illuminate\Database\Eloquent\Collection
    *
    * @example
    * // Array where
    * User::getUsers(['email' => 'john@example.com'], 10, 0, 'first_name ASC');
    *
    * // Multiple conditions
    * User::getUsers(['city' => 'New York', 'state' => 'NY'], 20, 0, 'last_name ASC');
    *
    * // All users (no where)
    * User::getUsers(null, 100, 0, 'created_at DESC');
    */
    public static function getUsers(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        // Start the query
        $query = static::with(['role', 'settings']);

        // Apply WHERE conditions (CI3 compatibility)
        if ($where !== null) {
            if (is_array($where)) {
                // Array format: ['column' => 'value'] or ['column' => ['operator', 'value']]
                $query->where($where);
            } elseif (is_string($where)) {
                // Raw SQL string (use with caution)
                $query->whereRaw($where);
            }
        }

        // Apply ordering (CI3 format: "column_name ASC" or "column_name DESC")
        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            // Validate direction
            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        // Apply limit and offset
        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        // Get results
        $users = $query->get();

        // If returning as array (CI3 compatibility)
        if ($asArray) {
            $usersArray = [];

            foreach ($users as $user) {
                $userData = $user->toArray();

                // Cast data types (CI3 compatibility)
                static::castUserData($userData);

                // Add settings as separate key (CI3 format)
                $userData['settings'] = static::getSettings($user->id);

                $usersArray[] = $userData;
            }

            return $usersArray;
        }

        // Return as Laravel collection
        return $users;
    }

    /**
    * Get all admins that match the provided criteria (role-specific wrapper).
    *
    * This method automatically filters by admin role and applies additional WHERE conditions.
    *
    * @param array|string|null $where Where conditions
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function getAdmins(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        // Start with admin scope
        $query = static::admins()->with(['role', 'settings']);

        // Apply WHERE conditions
        if ($where !== null) {
            if (is_array($where)) {
                $query->where($where);
            } elseif (is_string($where)) {
                $query->whereRaw($where);
            }
        }

        // Apply ordering
        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        // Apply limit and offset
        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        // Get results
        $admins = $query->get();

        // If returning as array
        if ($asArray) {
            $adminsArray = [];

            foreach ($admins as $admin) {
                $adminData = $admin->toArray();
                static::castUserData($adminData);
                $adminData['settings'] = static::getSettings($admin->id);
                $adminsArray[] = $adminData;
            }

            return $adminsArray;
        }

        return $admins;
    }

    /**
    * Get all providers that match the provided criteria (role-specific wrapper).
    *
    * @param array|string|null $where Where conditions
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function getProviders(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        $query = static::providers()->with(['role', 'settings', 'services']);

        if ($where !== null) {
            if (is_array($where)) {
                $query->where($where);
            } elseif (is_string($where)) {
                $query->whereRaw($where);
            }
        }

        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        $providers = $query->get();

        if ($asArray) {
            $providersArray = [];

            foreach ($providers as $provider) {
                $providerData = $provider->toArray();
                static::castUserData($providerData);
                $providerData['settings'] = static::getSettings($provider->id);
                $providersArray[] = $providerData;
            }

            return $providersArray;
        }

        return $providers;
    }

    /**
    * Get all secretaries that match the provided criteria (role-specific wrapper).
    *
    * @param array|string|null $where Where conditions
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function getSecretaries(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        $query = static::secretaries()->with(['role', 'settings', 'managedProviders']);

        if ($where !== null) {
            if (is_array($where)) {
                $query->where($where);
            } elseif (is_string($where)) {
                $query->whereRaw($where);
            }
        }

        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        $secretaries = $query->get();

        if ($asArray) {
            $secretariesArray = [];

            foreach ($secretaries as $secretary) {
                $secretaryData = $secretary->toArray();
                static::castUserData($secretaryData);
                $secretaryData['settings'] = static::getSettings($secretary->id);
                $secretariesArray[] = $secretaryData;
            }

            return $secretariesArray;
        }

        return $secretaries;
    }

    /**
    * Get all customers that match the provided criteria (role-specific wrapper).
    *
    * @param array|string|null $where Where conditions
    * @param int|null $limit Record limit
    * @param int|null $offset Record offset
    * @param string|null $orderBy Order by
    * @param bool $asArray Return as array or collection
    * @return array|\Illuminate\Database\Eloquent\Collection
    */
    public static function getCustomers(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        bool $asArray = false
    ): array|\Illuminate\Database\Eloquent\Collection {
        $query = static::customers()->with(['role', 'settings']);

        if ($where !== null) {
            if (is_array($where)) {
                $query->where($where);
            } elseif (is_string($where)) {
                $query->whereRaw($where);
            }
        }

        if (!empty($orderBy)) {
            $orderParts = explode(' ', trim($orderBy));
            $orderColumn = $orderParts[0];
            $orderDirection = strtolower($orderParts[1] ?? 'asc');

            if (!in_array($orderDirection, ['asc', 'desc'])) {
                $orderDirection = 'asc';
            }

            $query->orderBy($orderColumn, $orderDirection);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        $customers = $query->get();

        if ($asArray) {
            $customersArray = [];

            foreach ($customers as $customer) {
                $customerData = $customer->toArray();
                static::castUserData($customerData);
                $customerData['settings'] = static::getSettings($customer->id);
                $customersArray[] = $customerData;
            }

            return $customersArray;
        }

        return $customers;
    }

    /**
    * Count users matching criteria (useful for pagination).
    *
    * @param array|string|null $where Where conditions
    * @param string|null $roleSlug Filter by role (optional)
    * @return int Total count of matching records
    */
    public static function countUsers(array|string|null $where = null, ?string $roleSlug = null): int
    {
        $query = static::query();

        // Filter by role if provided
        if ($roleSlug) {
            $query->where('id_roles', match ($roleSlug) {
                'admin' => static::getAdminRoleId(),
                'provider' => static::getProviderRoleId(),
                'secretary' => static::getSecretaryRoleId(),
                'customer' => static::getCustomerRoleId(),
                default => throw new \InvalidArgumentException("Invalid role slug: {$roleSlug}")
            });
        }

        // Apply WHERE conditions
        if ($where !== null) {
            if (is_array($where)) {
                $query->where($where);
            } elseif (is_string($where)) {
                $query->whereRaw($where);
            }
        }

        return $query->count();
    }

    /**
    * Validation Methods (CI3 Compatibility)
    *
    * These methods handle validation of usernames, emails, and other unique fields.
    * Migrated from CI3 Users_model::validate_username() and Admins_model::validate_username()
    */

    /**
    * Check if a username is unique (CI3 strict compatibility).
    *
    * This method checks the user_settings table for username uniqueness.
    * Returns true if the username is available (does NOT exist).
    *
    * Migrated from CI3 Users_model::validate_username() and Admins_model::validate_username()
    *
    * @param string $username Username to check
    * @param int|null $excludeUserId User ID to exclude from check (for updates)
    * @return bool Returns true if username is unique (available), false if taken
    */
    public static function validateUsername(string $username, ?int $excludeUserId = null): bool
    {
        $query = UserSetting::where('username', $username);

        // Exclude a specific user (for updates)
        if (!empty($excludeUserId)) {
            $query->where('id_users', '!=', $excludeUserId);
        }

        // Return true if username is NOT found (i.e., it's available/unique)
        return $query->count() === 0;
    }


    /**
    * Check if a username is unique for admin (role-specific wrapper).
    *
    * @param string $username Username to check
    * @param int|null $adminId Admin ID to exclude
    * @return bool Returns true if username is unique
    */
    public static function validateAdminUsername(string $username, ?int $adminId = null): bool
    {
        // First check if username is unique
        if (!static::validateUsername($username, $adminId)) {
            return false;
        }

        // Additional admin-specific validation if needed
        return true;
    }

    /**
    * Check if a username is unique for provider (role-specific wrapper).
    *
    * @param string $username Username to check
    * @param int|null $providerId Provider ID to exclude
    * @return bool Returns true if username is unique
    */
    public static function validateProviderUsername(string $username, ?int $providerId = null): bool
    {
        return static::validateUsername($username, $providerId);
    }

    /**
    * Check if a username is unique for secretary (role-specific wrapper).
    *
    * @param string $username Username to check
    * @param int|null $secretaryId Secretary ID to exclude
    * @return bool Returns true if username is unique
    */
    public static function validateSecretaryUsername(string $username, ?int $secretaryId = null): bool
    {
        return static::validateUsername($username, $secretaryId);
    }

    /**
    * Check if a username is unique for customer (role-specific wrapper).
    *
    * @param string $username Username to check
    * @param int|null $customerId Customer ID to exclude
    * @return bool Returns true if username is unique
    */
    public static function validateCustomerUsername(string $username, ?int $customerId = null): bool
    {
        return static::validateUsername($username, $customerId);
    }

    /**
    * Get username by user ID.
    *
    * @param int $userId User ID
    * @return string|null Returns username or null if not found
    */
    public static function getUsernameByUserId(int $userId): ?string
    {
        $settings = UserSetting::where('id_users', $userId)->first();
        return $settings?->username;
    }

    /**
    * Get user ID by username.
    *
    * @param string $username Username
    * @return int|null Returns user ID or null if not found
    */
    public static function getUserIdByUsername(string $username): ?int
    {
        $settings = UserSetting::where('username', $username)->first();
        return $settings?->id_users;
    }

    /**
    * Check if a username exists (opposite of validateUsername).
    *
    * @param string $username Username to check
    * @return bool Returns true if username exists (taken), false if available
    */
    public static function usernameExists(string $username): bool
    {
        return !static::validateUsername($username);
    }

    /**
    * Find user by username.
    *
    * @param string $username Username
    * @return User|null Returns user or null if not found
    */
    public static function findByUsername(string $username): ?User
    {
        $settings = UserSetting::where('username', $username)->first();

        if (!$settings) {
            return null;
        }

        return static::with('settings')->find($settings->id_users);
    }

    // Keep the existing isUsernameUnique for backwards compatibility
    /**
    * Check if a username is unique (Laravel-style naming).
    *
    * Alias of validateUsername() for Laravel consistency.
    *
    * @param string $username Username to check
    * @param int|null $excludeUserId User ID to exclude
    * @return bool Returns true if username is unique
    */
    public static function isUsernameUnique(string $username, ?int $excludeUserId = null): bool
    {
        return static::validateUsername($username, $excludeUserId);
    }
}
