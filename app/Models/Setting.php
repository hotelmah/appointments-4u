<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Setting name constants for type safety and autocomplete.
     */
    // Company Information
    public const COMPANY_NAME = 'company_name';
    public const COMPANY_EMAIL = 'company_email';
    public const COMPANY_LINK = 'company_link';
    public const COMPANY_LOGO = 'company_logo';

    // Booking Settings
    public const DISPLAY_COOKIE_NOTICE = 'display_cookie_notice';
    public const COOKIE_NOTICE_CONTENT = 'cookie_notice_content';
    public const DISPLAY_TERMS_AND_CONDITIONS = 'display_terms_and_conditions';
    public const TERMS_AND_CONDITIONS_CONTENT = 'terms_and_conditions_content';
    public const DISPLAY_PRIVACY_POLICY = 'display_privacy_policy';
    public const PRIVACY_POLICY_CONTENT = 'privacy_policy_content';
    public const REQUIRE_PHONE_NUMBER = 'require_phone_number';
    public const CUSTOMER_NOTIFICATIONS = 'customer_notifications';
    public const DATE_FORMAT = 'date_format';
    public const TIME_FORMAT = 'time_format';
    public const FIRST_WEEKDAY = 'first_weekday';

    // Business Logic
    public const BOOK_ADVANCE_TIMEOUT = 'book_advance_timeout';
    public const FUTURE_BOOKING_LIMIT = 'future_booking_limit';
    public const DISABLE_BOOKING = 'disable_booking';
    public const DISABLE_BOOKING_MESSAGE = 'disable_booking_message';

    // Google Calendar Integration
    public const GOOGLE_SYNC_FEATURE = 'google_sync_feature';
    public const GOOGLE_CLIENT_ID = 'google_client_id';
    public const GOOGLE_CLIENT_SECRET = 'google_client_secret';
    public const GOOGLE_API_KEY = 'google_api_key';

    // CalDAV Integration
    public const CALDAV_ENABLED = 'caldav_enabled';

    // Notifications
    public const SEND_EMAIL_NOTIFICATIONS = 'send_email_notifications';

    /**
     * Boot method for model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Clear cache when settings are updated
        static::saved(function ($setting) {
            Cache::forget("setting_{$setting->name}");
            Cache::forget('all_settings');
        });

        static::deleted(function ($setting) {
            Cache::forget("setting_{$setting->name}");
            Cache::forget('all_settings');
        });
    }

    /**
     * Relationships
     */
    // Settings model doesn't have relationships as it's a key-value store

    /**
     * Accessors & Mutators
     */

    /**
     * Get the display-friendly name of the setting.
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->name));
    }

    /**
     * Check if this is a boolean setting.
     *
     * @return bool
     */
    public function getIsBooleanAttribute(): bool
    {
        return in_array($this->value, ['0', '1', 'true', 'false'], true);
    }

    /**
     * Get value as boolean.
     *
     * @return bool
     */
    public function getValueAsBoolAttribute(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get value as integer.
     *
     * @return int
     */
    public function getValueAsIntAttribute(): int
    {
        return (int) $this->value;
    }

    /**
     * Get short value (first 50 characters) for display.
     *
     * @return string
     */
    public function getShortValueAttribute(): string
    {
        if (!$this->value) {
            return '';
        }
        return strlen($this->value) > 50
            ? substr($this->value, 0, 50) . '...'
            : $this->value;
    }

    /**
     * Check if this is a system setting (cannot be deleted).
     *
     * @return bool
     */
    public function getIsSystemSettingAttribute(): bool
    {
        $systemSettings = [
            self::COMPANY_NAME,
            self::DATE_FORMAT,
            self::TIME_FORMAT,
        ];
        return in_array($this->name, $systemSettings);
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to find a setting by name.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Scope a query to find settings by name pattern.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $pattern
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNameLike($query, string $pattern)
    {
        return $query->where('name', 'like', $pattern);
    }

    /**
     * Scope a query to get company-related settings.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompanySettings($query)
    {
        return $query->where('name', 'like', 'company_%');
    }

    /**
     * Scope a query to get booking-related settings.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBookingSettings($query)
    {
        return $query->whereIn('name', [
            self::DISPLAY_COOKIE_NOTICE,
            self::DISPLAY_TERMS_AND_CONDITIONS,
            self::DISPLAY_PRIVACY_POLICY,
            self::REQUIRE_PHONE_NUMBER,
            self::CUSTOMER_NOTIFICATIONS,
            self::DATE_FORMAT,
            self::TIME_FORMAT,
            self::FIRST_WEEKDAY,
            self::BOOK_ADVANCE_TIMEOUT,
            self::FUTURE_BOOKING_LIMIT,
            self::DISABLE_BOOKING,
        ]);
    }

    /**
     * Scope a query to get integration-related settings.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIntegrationSettings($query)
    {
        return $query->where(function ($q) {
            $q->where('name', 'like', 'google_%')
              ->orWhere('name', 'like', 'caldav_%');
        });
    }

    /**
     * Scope a query to search settings by name or value.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('value', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Helper Methods
     */

    /**
     * Get a setting value by name.
     * Returns null if setting doesn't exist.
     *
     * @param string $name Setting name
     * @param mixed $default Default value if setting not found
     * @return mixed
     */
    public static function getValue(string $name, $default = null)
    {
        return Cache::remember("setting_{$name}", 3600, function () use ($name, $default) {
            $setting = static::byName($name)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Get a setting value as boolean.
     *
     * @param string $name Setting name
     * @param bool $default Default value
     * @return bool
     */
    public static function getBool(string $name, bool $default = false): bool
    {
        $value = static::getValue($name, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get a setting value as integer.
     *
     * @param string $name Setting name
     * @param int $default Default value
     * @return int
     */
    public static function getInt(string $name, int $default = 0): int
    {
        return (int) static::getValue($name, $default);
    }

    /**
     * Get a setting value as array (assumes JSON stored value).
     *
     * @param string $name Setting name
     * @param array $default Default value
     * @return array
     */
    public static function getArray(string $name, array $default = []): array
    {
        $value = static::getValue($name);
        if (!$value) {
            return $default;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Set a setting value by name.
     * Creates the setting if it doesn't exist.
     *
     * @param string $name Setting name
     * @param mixed $value Setting value
     * @return bool
     */
    public static function setValue(string $name, $value): bool
    {
        // Convert arrays/objects to JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $setting = static::updateOrCreate(
            ['name' => $name],
            ['value' => $value]
        );

        // Clear cache
        Cache::forget("setting_{$name}");
        Cache::forget('all_settings');

        return $setting->wasRecentlyCreated || $setting->wasChanged();
    }

    /**
     * Delete a setting by name.
     *
     * @param string $name Setting name
     * @return bool
     */
    public static function remove(string $name): bool
    {
        Cache::forget("setting_{$name}");
        Cache::forget('all_settings');
        return static::byName($name)->delete() > 0;
    }

    /**
     * Check if a setting exists.
     *
     * @param string $name Setting name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return static::byName($name)->exists();
    }

    /**
     * Get all settings as a key-value array.
     *
     * @return array
     */
    public static function getAllSettings(): array
    {
        return Cache::remember('all_settings', 3600, function () {
            return static::query()
                ->pluck('value', 'name')
                ->toArray();
        });
    }

    /**
     * Get multiple settings by names.
     *
     * @param array $names Array of setting names
     * @return array
     */
    public static function getMultiple(array $names): array
    {
        $settings = static::whereIn('name', $names)->pluck('value', 'name')->toArray();

        // Fill in missing settings with null
        foreach ($names as $name) {
            if (!isset($settings[$name])) {
                $settings[$name] = null;
            }
        }

        return $settings;
    }

    /**
     * Set multiple settings at once.
     *
     * @param array $settings Key-value pairs of settings
     * @return void
     */
    public static function setMultiple(array $settings): void
    {
        foreach ($settings as $name => $value) {
            static::setValue($name, $value);
        }
    }

    /**
     * Clear all settings cache.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        Cache::forget('all_settings');

        // Clear individual setting caches
        static::query()->get()->each(function ($setting) {
            Cache::forget("setting_{$setting->name}");
        });
    }

    /**
     * Check if setting can be deleted (not a system setting).
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return !$this->is_system_setting;
    }

    /**
     * Business Logic Helpers
     */

    /**
     * Check if bookings are currently disabled.
     *
     * @return bool
     */
    public static function isBookingDisabled(): bool
    {
        return static::getBool(self::DISABLE_BOOKING, false);
    }

    /**
     * Get the disabled booking message.
     *
     * @return string
     */
    public static function getDisabledBookingMessage(): string
    {
        return static::getValue(self::DISABLE_BOOKING_MESSAGE, 'Booking is currently disabled.');
    }

    /**
     * Check if Google Calendar sync is enabled.
     *
     * @return bool
     */
    public static function isGoogleSyncEnabled(): bool
    {
        return static::getBool(self::GOOGLE_SYNC_FEATURE, false);
    }

    /**
     * Check if CalDAV is enabled.
     *
     * @return bool
     */
    public static function isCalDavEnabled(): bool
    {
        return static::getBool(self::CALDAV_ENABLED, false);
    }

    /**
     * Check if email notifications are enabled.
     *
     * @return bool
     */
    public static function isEmailNotificationsEnabled(): bool
    {
        return static::getBool(self::SEND_EMAIL_NOTIFICATIONS, true);
    }

    /**
     * Get the company information as an array.
     *
     * @return array
     */
    public static function getCompanyInfo(): array
    {
        return [
            'name' => static::getValue(self::COMPANY_NAME),
            'email' => static::getValue(self::COMPANY_EMAIL),
            'link' => static::getValue(self::COMPANY_LINK),
            'logo' => static::getValue(self::COMPANY_LOGO),
        ];
    }

    /**
     * Get booking advance timeout in minutes.
     *
     * @return int
     */
    public static function getBookAdvanceTimeout(): int
    {
        return static::getInt(self::BOOK_ADVANCE_TIMEOUT, 0);
    }

    /**
     * Get future booking limit in days.
     *
     * @return int
     */
    public static function getFutureBookingLimit(): int
    {
        return static::getInt(self::FUTURE_BOOKING_LIMIT, 90);
    }

    /**
     * Get date format for display.
     *
     * @return string
     */
    public static function getDisplayDateFormat(): string
    {
        return static::getValue(self::DATE_FORMAT, 'Y-m-d');
    }

    /**
     * Get time format for display.
     *
     * @return string
     */
    public static function getDisplayTimeFormat(): string
    {
        return static::getValue(self::TIME_FORMAT, 'H:i');
    }

    /**
     * Get first day of week (0 = Sunday, 1 = Monday, etc.).
     *
     * @return int
     */
    public static function getFirstWeekday(): int
    {
        return static::getInt(self::FIRST_WEEKDAY, 0);
    }

    /**
     * Check if phone number is required for bookings.
     *
     * @return bool
     */
    public static function isPhoneRequired(): bool
    {
        return static::getBool(self::REQUIRE_PHONE_NUMBER, false);
    }

    /**
     * Check if cookie notice should be displayed.
     *
     * @return bool
     */
    public static function shouldDisplayCookieNotice(): bool
    {
        return static::getBool(self::DISPLAY_COOKIE_NOTICE, false);
    }

    /**
     * Check if terms and conditions should be displayed.
     *
     * @return bool
     */
    public static function shouldDisplayTermsAndConditions(): bool
    {
        return static::getBool(self::DISPLAY_TERMS_AND_CONDITIONS, false);
    }

    /**
     * Check if privacy policy should be displayed.
     *
     * @return bool
     */
    public static function shouldDisplayPrivacyPolicy(): bool
    {
        return static::getBool(self::DISPLAY_PRIVACY_POLICY, false);
    }
}
