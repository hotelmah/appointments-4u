<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class UserSetting extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id_users';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_users',
        'working_plan',
        'working_plan_exceptions',
        'notifications',
        'google_sync',
        'google_token',
        'google_calendar',
        'caldav_sync',
        'caldav_url',
        'caldav_username',
        'caldav_password',
        'sync_past_days',
        'sync_future_days',
        'calendar_view',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'google_token',
        'caldav_password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'working_plan' => 'array',
        'working_plan_exceptions' => 'array',
        'notifications' => 'boolean',
        'google_sync' => 'boolean',
        'caldav_sync' => 'boolean',
        'sync_past_days' => 'integer',
        'sync_future_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notifications' => true,
        'google_sync' => false,
        'caldav_sync' => false,
        'sync_past_days' => 30,
        'sync_future_days' => 90,
        'calendar_view' => 'default',
    ];

    /**
     * Calendar view type constants.
     */
    public const CALENDAR_VIEW_DEFAULT = 'default';
    public const CALENDAR_VIEW_TABLE = 'table';

    /**
     * Default working plan structure.
     */
    public const DEFAULT_WORKING_PLAN = [
        'monday' => [
            'start' => '09:00',
            'end' => '18:00',
            'breaks' => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
        ],
        'tuesday' => [
            'start' => '09:00',
            'end' => '18:00',
            'breaks' => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
        ],
        'wednesday' => [
            'start' => '09:00',
            'end' => '18:00',
            'breaks' => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
        ],
        'thursday' => [
            'start' => '09:00',
            'end' => '18:00',
            'breaks' => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
        ],
        'friday' => [
            'start' => '09:00',
            'end' => '18:00',
            'breaks' => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
        ],
        'saturday' => null, // Day off
        'sunday' => null,   // Day off
    ];

    /**
     * Relationships
     */

    /**
     * Get the user that owns the settings.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_users');
    }

    /**
     * Accessors & Mutators
     */

    /**
     * Get the CalDAV password (decrypt).
     */
    public function getCaldavPasswordAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the CalDAV password (encrypt).
     */
    public function setCaldavPasswordAttribute($value): void
    {
        if ($value) {
            $this->attributes['caldav_password'] = Crypt::encryptString($value);
        } else {
            $this->attributes['caldav_password'] = null;
        }
    }

    /**
     * Check if Google Calendar sync is enabled and configured.
     */
    public function getIsGoogleSyncConfiguredAttribute(): bool
    {
        return $this->google_sync && !empty($this->google_token);
    }

    /**
     * Check if CalDAV sync is enabled and configured.
     */
    public function getIsCalDavConfiguredAttribute(): bool
    {
        return $this->caldav_sync
            && !empty($this->caldav_url)
            && !empty($this->caldav_username)
            && !empty($this->caldav_password);
    }

    /**
     * Check if any calendar sync is active.
     */
    public function getHasActiveSyncAttribute(): bool
    {
        return $this->is_google_sync_configured || $this->is_cal_dav_configured;
    }

    /**
     * Get working plan for a specific day.
     */
    public function getWorkingPlanForDay(string $day): ?array
    {
        $day = strtolower($day);
        return $this->working_plan[$day] ?? null;
    }

    /**
     * Check if user is working on a specific day.
     */
    public function isWorkingOnDay(string $day): bool
    {
        return $this->getWorkingPlanForDay($day) !== null;
    }

    /**
     * Get the total sync range in days.
     */
    public function getTotalSyncRangeAttribute(): int
    {
        return $this->sync_past_days + $this->sync_future_days;
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to users with Google sync enabled.
     */
    public function scopeGoogleSyncEnabled($query)
    {
        return $query->where('google_sync', true)
            ->whereNotNull('google_token');
    }

    /**
     * Scope a query to users with CalDAV sync enabled.
     */
    public function scopeCalDavSyncEnabled($query)
    {
        return $query->where('caldav_sync', true)
            ->whereNotNull('caldav_url')
            ->whereNotNull('caldav_username')
            ->whereNotNull('caldav_password');
    }

    /**
     * Scope a query to users with any calendar sync enabled.
     */
    public function scopeAnySyncEnabled($query)
    {
        return $query->where(function ($q) {
            $q->where('google_sync', true)
              ->whereNotNull('google_token');
        })->orWhere(function ($q) {
            $q->where('caldav_sync', true)
              ->whereNotNull('caldav_url');
        });
    }

    /**
     * Scope a query to users with notifications enabled.
     */
    public function scopeNotificationsEnabled($query)
    {
        return $query->where('notifications', true);
    }

    /**
     * Scope a query by calendar view preference.
     */
    public function scopeByCalendarView($query, string $view)
    {
        return $query->where('calendar_view', $view);
    }

    /**
     * Helper Methods
     */

    /**
     * Get or create default settings for a user.
     *
     * @param int $userId
     * @return self
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return static::firstOrCreate(
            ['id_users' => $userId],
            [
                'working_plan' => self::DEFAULT_WORKING_PLAN,
                'notifications' => true,
                'calendar_view' => self::CALENDAR_VIEW_DEFAULT,
            ]
        );
    }

    /**
     * Initialize default working plan.
     *
     * @return bool
     */
    public function initializeDefaultWorkingPlan(): bool
    {
        if (!$this->working_plan) {
            $this->working_plan = self::DEFAULT_WORKING_PLAN;
            return $this->save();
        }
        return false;
    }

    /**
     * Update working plan for a specific day.
     *
     * @param string $day
     * @param array|null $schedule
     * @return bool
     */
    public function updateWorkingPlanForDay(string $day, ?array $schedule): bool
    {
        $day = strtolower($day);
        $workingPlan = $this->working_plan ?? [];
        $workingPlan[$day] = $schedule;
        $this->working_plan = $workingPlan;
        return $this->save();
    }

    /**
     * Add a working plan exception (e.g., holiday, special hours).
     *
     * @param string $date Date in Y-m-d format
     * @param array|null $schedule
     * @return bool
     */
    public function addWorkingPlanException(string $date, ?array $schedule): bool
    {
        $exceptions = $this->working_plan_exceptions ?? [];
        $exceptions[$date] = $schedule;
        $this->working_plan_exceptions = $exceptions;
        return $this->save();
    }

    /**
     * Remove a working plan exception.
     *
     * @param string $date Date in Y-m-d format
     * @return bool
     */
    public function removeWorkingPlanException(string $date): bool
    {
        $exceptions = $this->working_plan_exceptions ?? [];
        if (isset($exceptions[$date])) {
            unset($exceptions[$date]);
            $this->working_plan_exceptions = $exceptions;
            return $this->save();
        }
        return false;
    }

    /**
     * Get working schedule for a specific date (considering exceptions).
     *
     * @param string|\DateTime $date
     * @return array|null
     */
    public function getWorkingScheduleForDate($date): ?array
    {
        $dateString = is_string($date) ? $date : $date->format('Y-m-d');

        // Check for exceptions first
        if (isset($this->working_plan_exceptions[$dateString])) {
            return $this->working_plan_exceptions[$dateString];
        }

        // Fall back to regular working plan
        $dayOfWeek = is_string($date)
            ? strtolower(date('l', strtotime($date)))
            : strtolower($date->format('l'));

        return $this->getWorkingPlanForDay($dayOfWeek);
    }

    /**
     * Check if user is available on a specific date and time.
     *
     * @param string|\DateTime $date
     * @param string $time Time in H:i format
     * @return bool
     */
    public function isAvailableAt($date, string $time): bool
    {
        $schedule = $this->getWorkingScheduleForDate($date);

        if (!$schedule) {
            return false; // Not working this day
        }

        // Check if time is within working hours
        if ($time < $schedule['start'] || $time >= $schedule['end']) {
            return false;
        }

        // Check if time falls within a break
        if (isset($schedule['breaks'])) {
            foreach ($schedule['breaks'] as $break) {
                if ($time >= $break['start'] && $time < $break['end']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Enable Google Calendar sync.
     *
     * @param string $token
     * @param string|null $calendarId
     * @return bool
     */
    public function enableGoogleSync(string $token, ?string $calendarId = null): bool
    {
        $this->google_sync = true;
        $this->google_token = $token;
        if ($calendarId) {
            $this->google_calendar = $calendarId;
        }
        return $this->save();
    }

    /**
     * Disable Google Calendar sync.
     *
     * @return bool
     */
    public function disableGoogleSync(): bool
    {
        $this->google_sync = false;
        $this->google_token = null;
        $this->google_calendar = null;
        return $this->save();
    }

    /**
     * Enable CalDAV sync.
     *
     * @param string $url
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function enableCalDavSync(string $url, string $username, string $password): bool
    {
        $this->caldav_sync = true;
        $this->caldav_url = $url;
        $this->caldav_username = $username;
        $this->caldav_password = $password; // Will be encrypted by mutator
        return $this->save();
    }

    /**
     * Disable CalDAV sync.
     *
     * @return bool
     */
    public function disableCalDavSync(): bool
    {
        $this->caldav_sync = false;
        $this->caldav_url = null;
        $this->caldav_username = null;
        $this->caldav_password = null;
        return $this->save();
    }

    /**
     * Toggle notifications.
     *
     * @return bool
     */
    public function toggleNotifications(): bool
    {
        $this->notifications = !$this->notifications;
        return $this->save();
    }

    /**
     * Update sync range.
     *
     * @param int $pastDays
     * @param int $futureDays
     * @return bool
     */
    public function updateSyncRange(int $pastDays, int $futureDays): bool
    {
        $this->sync_past_days = max(0, $pastDays);
        $this->sync_future_days = max(0, $futureDays);
        return $this->save();
    }

    /**
     * Change calendar view preference.
     *
     * @param string $view
     * @return bool
     */
    public function setCalendarView(string $view): bool
    {
        $allowedViews = [self::CALENDAR_VIEW_DEFAULT, self::CALENDAR_VIEW_TABLE];

        if (in_array($view, $allowedViews)) {
            $this->calendar_view = $view;
            return $this->save();
        }

        return false;
    }

    /**
     * Get all working days from the working plan.
     *
     * @return array
     */
    public function getWorkingDays(): array
    {
        if (!$this->working_plan) {
            return [];
        }

        return array_keys(array_filter($this->working_plan, function ($schedule) {
            return $schedule !== null;
        }));
    }

    /**
     * Get all non-working days from the working plan.
     *
     * @return array
     */
    public function getNonWorkingDays(): array
    {
        if (!$this->working_plan) {
            return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        }

        return array_keys(array_filter($this->working_plan, function ($schedule) {
            return $schedule === null;
        }));
    }

    /**
     * Validate working plan structure.
     *
     * @param array $workingPlan
     * @return bool
     */
    public static function isValidWorkingPlan(array $workingPlan): bool
    {
        $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($validDays as $day) {
            if (!isset($workingPlan[$day])) {
                return false;
            }

            // If the day is not null, validate its structure
            if ($workingPlan[$day] !== null) {
                if (!isset($workingPlan[$day]['start']) || !isset($workingPlan[$day]['end'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Static helper to get settings for a user.
     *
     * @param int $userId
     * @return array
     */
    public static function getForUser(int $userId): array
    {
        $settings = static::where('id_users', $userId)->first();

        if (!$settings) {
            return [];
        }

        $settingsArray = $settings->toArray();

        // Remove sensitive fields
        unset($settingsArray['id_users']);

        return $settingsArray;
    }

    /**
     * Static helper to set all settings for a user.
     *
     * @param int $userId
     * @param array $settingsData
     * @return void
     */
    public static function setForUser(int $userId, array $settingsData): void
    {
        $settings = static::where('id_users', $userId)->first();

        if (!$settings) {
            $settings = static::create(array_merge(['id_users' => $userId], $settingsData));
        } else {
            $settings->update($settingsData);
        }
    }

    /**
     * Static helper to set a single setting for a user.
     *
     * @param int $userId
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function setForUserKey(int $userId, string $key, mixed $value): void
    {
        $settings = static::where('id_users', $userId)->first();

        if (!$settings) {
            $settings = static::create(['id_users' => $userId, $key => $value]);
        } else {
            $settings->update([$key => $value]);
        }
    }

    /**
     * Static helper to get a single setting value.
     *
     * @param int $userId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getForUserKey(int $userId, string $key, mixed $default = null): mixed
    {
        $settings = static::where('id_users', $userId)->first();

        if (!$settings) {
            return $default;
        }

        return $settings->{$key} ?? $default;
    }
}
