<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'is_admin',
        'appointments',
        'customers',
        'services',
        'users',
        'system_settings',
        'user_settings',
        'webhooks',
        'blocked_periods',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_admin' => 'boolean',
        'appointments' => 'integer',
        'customers' => 'integer',
        'services' => 'integer',
        'users' => 'integer',
        'system_settings' => 'integer',
        'user_settings' => 'integer',
        'webhooks' => 'integer',
        'blocked_periods' => 'integer',
    ];

    /**
     * Permission level constants.
     */
    public const PERMISSION_NONE = 0;
    public const PERMISSION_VIEW = 1;
    public const PERMISSION_ADD = 2;
    public const PERMISSION_EDIT = 4;
    public const PERMISSION_DELETE = 8;
    public const PERMISSION_FULL = 15; // 1 + 2 + 4 + 8

    /**
     * Relationships
     */

    /**
     * Get the users that have this role.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'id_roles');
    }

    /**
     * Accessors & Mutators
     */

    /**
     * Get the role display name with admin indicator.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->is_admin ? "{$this->name} (Admin)" : $this->name;
    }

    /**
     * Check if role has any permissions set.
     */
    public function getHasPermissionsAttribute(): bool
    {
        return $this->appointments > 0
            || $this->customers > 0
            || $this->services > 0
            || $this->users > 0
            || $this->system_settings > 0
            || $this->user_settings > 0
            || $this->webhooks > 0
            || $this->blocked_periods > 0;
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to only include admin roles.
     */
    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    /**
     * Scope a query to only include non-admin roles.
     */
    public function scopeNonAdmins($query)
    {
        return $query->where('is_admin', false);
    }

    /**
     * Scope a query to roles by slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope a query to roles with specific permission level for a module.
     */
    public function scopeWithPermission($query, string $module, int $minLevel = self::PERMISSION_VIEW)
    {
        return $query->where($module, '>=', $minLevel);
    }

    /**
     * Permission Check Methods
     */

    /**
     * Check if role has a specific permission level for a module.
     */
    public function hasPermission(string $module, int $level): bool
    {
        if ($this->is_admin) {
            return true; // Admins have all permissions
        }

        if (!property_exists($this, $module) && !array_key_exists($module, $this->attributes)) {
            return false;
        }

        return ($this->$module & $level) === $level;
    }

    /**
     * Check if role can view a module.
     */
    public function canView(string $module): bool
    {
        return $this->hasPermission($module, self::PERMISSION_VIEW);
    }

    /**
     * Check if role can add to a module.
     */
    public function canAdd(string $module): bool
    {
        return $this->hasPermission($module, self::PERMISSION_ADD);
    }

    /**
     * Check if role can edit a module.
     */
    public function canEdit(string $module): bool
    {
        return $this->hasPermission($module, self::PERMISSION_EDIT);
    }

    /**
     * Check if role can delete from a module.
     */
    public function canDelete(string $module): bool
    {
        return $this->hasPermission($module, self::PERMISSION_DELETE);
    }

    /**
     * Check if role has full access to a module.
     */
    public function hasFullAccess(string $module): bool
    {
        return $this->is_admin || $this->$module === self::PERMISSION_FULL;
    }

    /**
     * Get all modules this role has access to.
     */
    public function getAccessibleModules(): array
    {
        $modules = [
            'appointments',
            'customers',
            'services',
            'users',
            'system_settings',
            'user_settings',
            'webhooks',
            'blocked_periods',
        ];

        return array_filter($modules, function ($module) {
            return $this->$module > 0;
        });
    }

    /**
     * Get permission level for a specific module.
     */
    public function getPermissionLevel(string $module): int
    {
        if ($this->is_admin) {
            return self::PERMISSION_FULL;
        }

        return $this->$module ?? 0;
    }

    /**
     * Get human-readable permission description for a module.
     */
    public function getPermissionDescription(string $module): string
    {
        if ($this->is_admin) {
            return 'Full Access';
        }

        $level = $this->getPermissionLevel($module);

        if ($level === 0) {
            return 'No Access';
        }

        $permissions = [];
        if ($level & self::PERMISSION_VIEW) {
            $permissions[] = 'View';
        }
        if ($level & self::PERMISSION_ADD) {
            $permissions[] = 'Add';
        }
        if ($level & self::PERMISSION_EDIT) {
            $permissions[] = 'Edit';
        }
        if ($level & self::PERMISSION_DELETE) {
            $permissions[] = 'Delete';
        }

        return implode(', ', $permissions);
    }

    /**
     * Get all permissions as an array.
     */
    public function getAllPermissions(): array
    {
        return [
            'appointments' => [
                'level' => $this->appointments,
                'description' => $this->getPermissionDescription('appointments'),
            ],
            'customers' => [
                'level' => $this->customers,
                'description' => $this->getPermissionDescription('customers'),
            ],
            'services' => [
                'level' => $this->services,
                'description' => $this->getPermissionDescription('services'),
            ],
            'users' => [
                'level' => $this->users,
                'description' => $this->getPermissionDescription('users'),
            ],
            'system_settings' => [
                'level' => $this->system_settings,
                'description' => $this->getPermissionDescription('system_settings'),
            ],
            'user_settings' => [
                'level' => $this->user_settings,
                'description' => $this->getPermissionDescription('user_settings'),
            ],
            'webhooks' => [
                'level' => $this->webhooks,
                'description' => $this->getPermissionDescription('webhooks'),
            ],
            'blocked_periods' => [
                'level' => $this->blocked_periods,
                'description' => $this->getPermissionDescription('blocked_periods'),
            ],
        ];
    }

    /**
     * Helper Methods
     */

    /**
     * Set permission level for a module.
     */
    public function setPermission(string $module, int $level): bool
    {
        if (in_array($module, $this->fillable)) {
            $this->$module = $level;
            return $this->save();
        }
        return false;
    }

    /**
     * Grant a specific permission to a module.
     */
    public function grantPermission(string $module, int $permission): bool
    {
        if (in_array($module, $this->fillable)) {
            $this->$module = $this->$module | $permission;
            return $this->save();
        }
        return false;
    }

    /**
     * Revoke a specific permission from a module.
     */
    public function revokePermission(string $module, int $permission): bool
    {
        if (in_array($module, $this->fillable)) {
            $this->$module = $this->$module & ~$permission;
            return $this->save();
        }
        return false;
    }

    /**
     * Check if this is a system default role (admin, provider, secretary, customer).
     */
    public function isSystemRole(): bool
    {
        return in_array($this->slug, ['administrator', 'provider', 'secretary', 'customer']);
    }

    /**
     * Get count of users with this role.
     */
    public function getUsersCountAttribute(): int
    {
        return $this->users()->count();
    }
}
