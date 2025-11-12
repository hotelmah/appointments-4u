<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class BlockedPeriod extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'start_datetime',
        'end_datetime',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'created_at' => 'datetime',  // ✅ ADDED
        'updated_at' => 'datetime',  // ✅ ADDED
    ];

    /**
     * Relationships
     */
    // BlockedPeriod doesn't have direct relationships

    /**
     * Accessors & Mutators
     */

    /**
     * Get the blocked period duration in minutes.
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->start_datetime && $this->end_datetime) {
            return $this->start_datetime->diffInMinutes($this->end_datetime);
        }
        return null;
    }

    /**
     * Get the blocked period duration in days.
     */
    public function getDurationInDaysAttribute(): ?int
    {
        if ($this->start_datetime && $this->end_datetime) {
            return $this->start_datetime->diffInDays($this->end_datetime);
        }
        return null;
    }

    /**
     * Check if blocked period is in the past.
     */
    public function getIsPastAttribute(): bool
    {
        return $this->end_datetime && $this->end_datetime->isPast();
    }

    /**
     * Check if blocked period is upcoming.
     */
    public function getIsUpcomingAttribute(): bool
    {
        return $this->start_datetime && $this->start_datetime->isFuture();
    }

    /**
     * Check if blocked period is currently active.
     */
    public function getIsActiveAttribute(): bool
    {
        if ($this->start_datetime && $this->end_datetime) {
            return now()->between($this->start_datetime, $this->end_datetime);
        }
        return false;
    }

    /**
     * Get a human-readable date range.
     */
    public function getDateRangeAttribute(): string
    {
        if ($this->start_datetime && $this->end_datetime) {
            if ($this->start_datetime->isSameDay($this->end_datetime)) {
                // Same day
                return $this->start_datetime->format('M d, Y') . ' (' .
                       $this->start_datetime->format('g:i A') . ' - ' .
                       $this->end_datetime->format('g:i A') . ')';
            } else {
                // Multiple days
                return $this->start_datetime->format('M d, Y g:i A') . ' - ' .
                       $this->end_datetime->format('M d, Y g:i A');
            }
        }
        return '';
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to only include active blocked periods.
     */
    public function scopeActive($query)
    {
        return $query->where('start_datetime', '<=', now())
                     ->where('end_datetime', '>=', now());
    }

    /**
     * Scope a query to only include upcoming blocked periods.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>', now());
    }

    /**
     * Scope a query to only include past blocked periods.
     */
    public function scopePast($query)
    {
        return $query->where('end_datetime', '<', now());
    }

    /**
     * Scope a query to blocked periods within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_datetime', [$startDate, $endDate])
              ->orWhereBetween('end_datetime', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_datetime', '<=', $startDate)
                     ->where('end_datetime', '>=', $endDate);
              });
        });
    }

    /**
     * Scope a query to blocked periods that overlap with a specific date range.
     */
    public function scopeOverlapping($query, $startDate, $endDate)
    {
        return $query->where('start_datetime', '<', $endDate)
                     ->where('end_datetime', '>', $startDate);
    }

    /**
     * Scope a query to today's blocked periods.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('start_datetime', '<=', today())
                     ->whereDate('end_datetime', '>=', today());
    }

    /**
     * Scope a query to this week's blocked periods.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_datetime', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope a query to this month's blocked periods.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('start_datetime', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }

    /**
     * Helper Methods
     */

    /**
     * Check if this blocked period overlaps with another blocked period.
     */
    public function overlapsWith(BlockedPeriod $other): bool
    {
        return $this->start_datetime < $other->end_datetime
            && $this->end_datetime > $other->start_datetime;
    }

    /**
     * Check if this blocked period contains a specific datetime.
     */
    public function contains($datetime): bool
    {
        $datetime = is_string($datetime) ? Carbon::parse($datetime) : $datetime;
        return $datetime->between($this->start_datetime, $this->end_datetime);
    }

    /**
     * Check if this blocked period overlaps with a given date range.
     */
    public function overlapsWithRange($startDate, $endDate): bool
    {
        $startDate = is_string($startDate) ? Carbon::parse($startDate) : $startDate;
        $endDate = is_string($endDate) ? Carbon::parse($endDate) : $endDate;

        return $this->start_datetime < $endDate && $this->end_datetime > $startDate;
    }

    /**
     * Check if appointments can be scheduled during this period.
     */
    public function blocksAppointments(): bool
    {
        return $this->is_active || $this->is_upcoming;
    }

    /**
    * Validation
    */

    /**
    * Validate blocked period data before saving.
    *
    * This method contains business logic validation beyond Laravel's basic validation rules.
    * Migrated from CI3 Blocked_periods_model::validate()
    *
    * @param array $data Blocked period data to validate
    * @param int|null $blockedPeriodId Blocked period ID (for updates, to exclude from checks)
    * @throws \InvalidArgumentException
    */
    public static function validateBlockedPeriodData(array $data, ?int $blockedPeriodId = null): void
    {
        // If a blocked-period ID is provided, check if it exists
        if (!empty($data['id']) && $data['id'] !== $blockedPeriodId) {
            $exists = static::where('id', $data['id'])->exists();

            if (!$exists) {
                throw new \InvalidArgumentException(
                    "The provided blocked period ID does not exist in the database: {$data['id']}"
                );
            }
        }

        // Make sure all required fields are provided
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('The blocked period name is required.');
        }

        if (empty($data['start_datetime'])) {
            throw new \InvalidArgumentException('The start date time is required.');
        }

        if (empty($data['end_datetime'])) {
            throw new \InvalidArgumentException('The end date time is required.');
        }

        // Validate that start_datetime is a valid date
        try {
            $startDateTime = Carbon::parse($data['start_datetime']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('The start date time is invalid.');
        }

        // Validate that end_datetime is a valid date
        try {
            $endDateTime = Carbon::parse($data['end_datetime']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('The end date time is invalid.');
        }

        // Make sure that the start date time is before the end
        if ($startDateTime >= $endDateTime) {
            throw new \InvalidArgumentException(
                'The start date time must be before the end date time.'
            );
        }

        // Optional: Check for overlapping blocked periods
        $overlappingQuery = static::where('start_datetime', '<', $endDateTime)
            ->where('end_datetime', '>', $startDateTime);

        // Exclude current blocked period when updating
        if ($blockedPeriodId) {
            $overlappingQuery->where('id', '!=', $blockedPeriodId);
        }

        $overlappingPeriods = $overlappingQuery->get();

        if ($overlappingPeriods->isNotEmpty()) {
            $overlappingNames = $overlappingPeriods->pluck('name')->join(', ');
            throw new \InvalidArgumentException(
                "This blocked period overlaps with existing blocked period(s): {$overlappingNames}"
            );
        }
    }

    /**
    * Validate database relationships (if any are added in the future).
    *
    * @param array $data Blocked period data
    * @throws \InvalidArgumentException
    */
    public static function validateBlockedPeriodRelationships(array $data): void
    {
        // Currently blocked periods don't have relationships
        // This method is a placeholder for future enhancements

        // Example if you add provider relationships in the future:
        // if (!empty($data['id_users_provider'])) {
        //     $providerExists = User::where('id', $data['id_users_provider'])
        //         ->where('id_roles', DB::raw('(SELECT id FROM roles WHERE slug = "provider")'))
        //         ->exists();
        //
        //     if (!$providerExists) {
        //         throw new \InvalidArgumentException('The provided provider ID is invalid.');
        //     }
        // }
    }

    /**
    * Check if the blocked period dates are valid (end after start).
    */
    public function hasValidDates(): bool
    {
        return $this->start_datetime && $this->end_datetime
            && $this->end_datetime->greaterThan($this->start_datetime);
    }

    /**
    * Check if this blocked period overlaps with existing periods.
    *
    * @param int|null $excludeId Blocked period ID to exclude from check
    * @return bool
    */
    public function hasOverlaps(?int $excludeId = null): bool
    {
        $query = static::where('start_datetime', '<', $this->end_datetime)
            ->where('end_datetime', '>', $this->start_datetime);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        } elseif ($this->id) {
            $query->where('id', '!=', $this->id);
        }

        return $query->exists();
    }

    /**
    * Get overlapping blocked periods.
    *
    * @param int|null $excludeId Blocked period ID to exclude from results
    * @return \Illuminate\Database\Eloquent\Collection
    */
    public function getOverlappingPeriods(?int $excludeId = null)
    {
        $query = static::where('start_datetime', '<', $this->end_datetime)
            ->where('end_datetime', '>', $this->start_datetime);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        } elseif ($this->id) {
            $query->where('id', '!=', $this->id);
        }

        return $query->get();
    }

    /**
     * CRUD Helper Methods
     */

    /**
     * Find a blocked period by ID with custom error handling.
     *
     * This method maintains CI3 compatibility by throwing InvalidArgumentException
     * instead of Laravel's ModelNotFoundException.
     * Migrated from CI3 Blocked_periods_model::find()
     *
     * @param int $blockedPeriodId The ID of the blocked period
     * @return BlockedPeriod
     * @throws \InvalidArgumentException If blocked period not found
     */
    public static function findOrThrow(int $blockedPeriodId): BlockedPeriod
    {
        $blockedPeriod = static::find($blockedPeriodId);

        if (!$blockedPeriod) {
            throw new \InvalidArgumentException(
                "The provided blocked period ID was not found in the database: {$blockedPeriodId}"
            );
        }

        return $blockedPeriod;
    }

    /**
     * Get a specific field value from a blocked period.
     *
     * This method maintains CI3 compatibility for retrieving individual field values.
     * Migrated from CI3 Blocked_periods_model::value()
     *
     * @param int $blockedPeriodId Blocked period ID
     * @param string $field Name of the field to retrieve
     * @return mixed Returns the field value
     * @throws \InvalidArgumentException
     */
    public static function getValue(int $blockedPeriodId, string $field): mixed
    {
        // Validate field parameter
        if (empty($field)) {
            throw new \InvalidArgumentException('The field argument cannot be empty.');
        }

        // Validate ID parameter
        if (empty($blockedPeriodId)) {
            throw new \InvalidArgumentException('The blocked period ID argument cannot be empty.');
        }

        // Find the blocked period
        $blockedPeriod = static::find($blockedPeriodId);

        if (!$blockedPeriod) {
            throw new \InvalidArgumentException(
                "The provided blocked period ID was not found in the database: {$blockedPeriodId}"
            );
        }

        // Check if the field exists in the model
        if (!array_key_exists($field, $blockedPeriod->getAttributes())) {
            throw new \InvalidArgumentException(
                "The requested field was not found in the blocked period data: {$field}"
            );
        }

        // Return the field value (using getAttribute to handle casts and accessors)
        return $blockedPeriod->getAttribute($field);
    }

    /**
     * Get multiple field values from a blocked period.
     *
     * Enhanced version that can retrieve multiple fields at once.
     *
     * @param int $blockedPeriodId Blocked period ID
     * @param array $fields Array of field names to retrieve
     * @return array Returns array of field values
     * @throws \InvalidArgumentException
     */
    public static function getValues(int $blockedPeriodId, array $fields): array
    {
        if (empty($fields)) {
            throw new \InvalidArgumentException('The fields array cannot be empty.');
        }

        if (empty($blockedPeriodId)) {
            throw new \InvalidArgumentException('The blocked period ID argument cannot be empty.');
        }

        $blockedPeriod = static::find($blockedPeriodId);

        if (!$blockedPeriod) {
            throw new \InvalidArgumentException(
                "The provided blocked period ID was not found in the database: {$blockedPeriodId}"
            );
        }

        $result = [];
        $attributes = $blockedPeriod->getAttributes();

        foreach ($fields as $field) {
            if (!array_key_exists($field, $attributes)) {
                throw new \InvalidArgumentException(
                    "The requested field was not found in the blocked period data: {$field}"
                );
            }
            $result[$field] = $blockedPeriod->getAttribute($field);
        }

        return $result;
    }

    /**
     * Pluck a specific field value from multiple blocked periods.
     *
     * Get a single field from multiple records at once (more efficient).
     *
     * @param array $blockedPeriodIds Array of blocked period IDs
     * @param string $field Field name to retrieve
     * @return array Returns array of [id => value] pairs
     * @throws \InvalidArgumentException
     */
    public static function pluckField(array $blockedPeriodIds, string $field): array
    {
        if (empty($field)) {
            throw new \InvalidArgumentException('The field argument cannot be empty.');
        }

        if (empty($blockedPeriodIds)) {
            throw new \InvalidArgumentException('The blocked period IDs array cannot be empty.');
        }

        // Validate that field exists by checking against fillable or database columns
        $instance = new static();

        if (!in_array($field, $instance->getFillable()) && !Schema::hasColumn($instance->getTable(), $field)) {
            throw new \InvalidArgumentException(
                "The requested field was not found in the blocked period schema: {$field}"
            );
        }

        return static::whereIn('id', $blockedPeriodIds)->pluck($field, 'id')->toArray();
    }

    /**
     * Search Methods
     */

    /**
     * Search blocked periods by keyword.
     *
     * Searches through name and notes fields.
     * Migrated from CI3 Blocked_periods_model::search()
     *
     * @param string $keyword Search keyword
     * @param int|null $limit Record limit
     * @param int|null $offset Record offset
     * @param string|null $orderBy Order by clause (e.g., 'start_datetime DESC')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function search(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null
    ) {
        $query = static::where(function ($q) use ($keyword) {
            $q->where('name', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%");
        });

        // Apply ordering
        if ($orderBy) {
            $parts = explode(' ', trim($orderBy));
            $column = $parts[0];
            $direction = $parts[1] ?? 'asc';
            $query->orderBy($column, $direction);
        } else {
            // Default ordering: most recent updates first
            $query->orderBy('updated_at', 'desc');
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
     * Scope for searching blocked periods (chainable with other scopes).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $keyword
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchByKeyword($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'LIKE', "%{$keyword}%")
              ->orWhere('notes', 'LIKE', "%{$keyword}%")
              ->orWhere('start_datetime', 'LIKE', "%{$keyword}%")
              ->orWhere('end_datetime', 'LIKE', "%{$keyword}%");
        });
    }

    /**
     * Advanced search with multiple filters.
     *
     * More flexible search method that supports multiple search criteria.
     *
     * @param array $filters Search filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function advancedSearch(array $filters = [])
    {
        $query = static::query();

        // Keyword search
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('notes', 'LIKE', "%{$keyword}%");
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'active':
                    $query->active();
                    break;
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'past':
                    $query->past();
                    break;
            }
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->whereDate('start_datetime', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('end_datetime', '<=', $filters['end_date']);
        }

        // Apply ordering
        $orderBy = $filters['order_by'] ?? 'updated_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        // Apply pagination
        $limit = $filters['limit'] ?? 1000;
        $offset = $filters['offset'] ?? 0;

        return $query->limit($limit)->offset($offset)->get();
    }

    /**
     * Query Helper Methods
     */

    /**
     * Get blocked periods with flexible filtering.
     *
     * This method provides CI3-style querying with where conditions, limits, and ordering.
     * Migrated from CI3 Blocked_periods_model::get()
     *
     * @param array|string|null $where Where conditions (array or raw string)
     * @param int|null $limit Record limit
     * @param int|null $offset Record offset
     * @param string|null $orderBy Order by clause (e.g., 'start_datetime DESC')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getFiltered(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null
    ) {
        $query = static::query();

        // Apply where conditions
        if ($where !== null) {
            if (is_array($where)) {
                // Array of conditions: ['status' => 'active', 'name' => 'Holiday']
                $query->where($where);
            } elseif (is_string($where)) {
                // Raw SQL string (use with caution)
                $query->whereRaw($where);
            }
        }

        // Apply ordering
        if ($orderBy !== null) {
            $parts = explode(' ', trim($orderBy));
            $column = $parts[0];
            $direction = $parts[1] ?? 'asc';
            $query->orderBy($column, $direction);
        } else {
            // Default ordering
            $query->orderBy('start_datetime', 'asc');
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
     * Get all blocked periods (convenience method).
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAll()
    {
        return static::orderBy('start_datetime', 'asc')->get();
    }

    /**
     * Get blocked periods by specific criteria with pagination support.
     *
     * Enhanced version with better Laravel integration.
     *
     * @param array $criteria Filtering criteria
     * @param array $options Query options (limit, offset, order_by, paginate)
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getByCriteria(array $criteria = [], array $options = [])
    {
        $query = static::query();

        // Apply filters from criteria
        foreach ($criteria as $field => $value) {
            if ($value !== null) {
                if (is_array($value)) {
                    // Handle IN queries: ['id' => [1, 2, 3]]
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        // Apply ordering
        $orderBy = $options['order_by'] ?? 'start_datetime';
        $orderDirection = $options['order_direction'] ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);

        // Apply pagination or limit/offset
        if (!empty($options['paginate'])) {
            $perPage = $options['per_page'] ?? 15;
            return $query->paginate($perPage);
        }

        if (!empty($options['limit'])) {
            $query->limit($options['limit']);
        }

        if (!empty($options['offset'])) {
            $query->offset($options['offset']);
        }

        return $query->get();
    }

    /**
     * Get blocked periods with complex where conditions.
     *
     * Supports closure-based queries for complex conditions.
     *
     * @param \Closure|null $whereCallback Closure for complex where conditions
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $orderBy
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getWithCallback(
        ?\Closure $whereCallback = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null
    ) {
        $query = static::query();

        if ($whereCallback !== null) {
            $query->where($whereCallback);
        }

        if ($orderBy !== null) {
            $parts = explode(' ', trim($orderBy));
            $column = $parts[0];
            $direction = $parts[1] ?? 'asc';
            $query->orderBy($column, $direction);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        return $query->get();
    }

    /**
     * Period-based Query Methods
     */

    /**
     * Get all blocked periods that overlap with or fall within the provided period.
     *
     * This method finds blocked periods that:
     * 1. Completely contain the provided period
     * 2. Fall completely within the provided period
     * 3. Start before the period and end within it
     * 4. Start within the period and end after it
     *
     * Migrated from CI3 Blocked_periods_model::get_for_period()
     *
     * @param string $startDate Start date (YYYY-MM-DD or datetime)
     * @param string $endDate End date (YYYY-MM-DD or datetime)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getForPeriod(string $startDate, string $endDate)
    {
        return static::where(function ($query) use ($startDate, $endDate) {
            // Case 1: Blocked period completely contains the search period
            // (starts before or on start_date AND ends after or on end_date)
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereRaw('DATE(start_datetime) <= ?', [$startDate])
                  ->whereRaw('DATE(end_datetime) >= ?', [$endDate]);
            })
            // Case 2: Blocked period falls completely within the search period
            // (starts on or after start_date AND ends on or before end_date)
            ->orWhere(function ($q) use ($startDate, $endDate) {
                $q->whereRaw('DATE(start_datetime) >= ?', [$startDate])
                  ->whereRaw('DATE(end_datetime) <= ?', [$endDate]);
            })
            // Case 3: Blocked period ends within the search period
            // (ends after start_date AND ends before end_date)
            ->orWhere(function ($q) use ($startDate, $endDate) {
                $q->whereRaw('DATE(end_datetime) > ?', [$startDate])
                  ->whereRaw('DATE(end_datetime) < ?', [$endDate]);
            })
            // Case 4: Blocked period starts within the search period
            // (starts after start_date AND starts before end_date)
            ->orWhere(function ($q) use ($startDate, $endDate) {
                $q->whereRaw('DATE(start_datetime) > ?', [$startDate])
                  ->whereRaw('DATE(start_datetime) < ?', [$endDate]);
            });
        })->get();
    }

    /**
     * Scope for querying blocked periods within a specific period.
     *
     * Chainable version of getForPeriod() for more flexible queries.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPeriod($query, string $startDate, string $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            // Case 1: Contains the period
            $q->where(function ($subQ) use ($startDate, $endDate) {
                $subQ->whereRaw('DATE(start_datetime) <= ?', [$startDate])
                     ->whereRaw('DATE(end_datetime) >= ?', [$endDate]);
            })
            // Case 2: Within the period
            ->orWhere(function ($subQ) use ($startDate, $endDate) {
                $subQ->whereRaw('DATE(start_datetime) >= ?', [$startDate])
                     ->whereRaw('DATE(end_datetime) <= ?', [$endDate]);
            })
            // Case 3: Ends within period
            ->orWhere(function ($subQ) use ($startDate, $endDate) {
                $subQ->whereRaw('DATE(end_datetime) > ?', [$startDate])
                     ->whereRaw('DATE(end_datetime) < ?', [$endDate]);
            })
            // Case 4: Starts within period
            ->orWhere(function ($subQ) use ($startDate, $endDate) {
                $subQ->whereRaw('DATE(start_datetime) > ?', [$startDate])
                     ->whereRaw('DATE(start_datetime) < ?', [$endDate]);
            });
        });
    }

    /**
     * Check if there are any blocked periods within the specified date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return bool
     */
    public static function hasBlockedPeriodsInRange(string $startDate, string $endDate): bool
    {
        return static::forPeriod($startDate, $endDate)->exists();
    }

    /**
     * Get count of blocked periods within a date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    public static function countForPeriod(string $startDate, string $endDate): int
    {
        return static::forPeriod($startDate, $endDate)->count();
    }

    /**
     * Get blocked periods for a specific date (single day).
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getForDate(string $date)
    {
        return static::whereRaw('DATE(start_datetime) <= ?', [$date])
                     ->whereRaw('DATE(end_datetime) >= ?', [$date])
                     ->get();
    }

    /**
     * Check if a specific date has any blocking periods.
     *
     * This checks if ANY blocked period affects the given date.
     * Migrated from CI3 Blocked_periods_model::is_entire_date_blocked()
     * (but fixing the > 1 bug to > 0)
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return bool
     */
    public static function isDateBlocked(string $date): bool
    {
        return static::whereRaw('DATE(start_datetime) <= ?', [$date])
                     ->whereRaw('DATE(end_datetime) >= ?', [$date])
                     ->exists();  // ✅ Returns true if ANY blocked period covers this date
    }

    /**
     * Check if an entire date is blocked (full 24-hour period).
     *
     * This checks if a blocked period covers the ENTIRE day from 00:00:00 to 23:59:59.
     * This is the corrected implementation of what CI3 probably intended.
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return bool
     */
    public static function isEntireDateBlocked(string $date): bool
    {
        // Method 1: Check if blocked period covers full day (00:00 to 23:59)
        return static::whereRaw('DATE(start_datetime) <= ?', [$date])
                     ->whereRaw('DATE(end_datetime) >= ?', [$date])
                     ->whereRaw('TIME(start_datetime) <= ?', ['00:00:00'])
                     ->whereRaw('TIME(end_datetime) >= ?', ['23:59:59'])
                     ->exists();
    }

    /**
     * Alternative implementation: Check if entire working day is blocked.
     *
     * This checks if blocked periods cover the entire working day
     * (based on business hours, not literal 24 hours).
     *
     * @param string $date Date to check
     * @param string $workStart Business hours start (default 00:00:00 for full day)
     * @param string $workEnd Business hours end (default 23:59:59 for full day)
     * @return bool
     */
    public static function isWorkingDayBlocked(
        string $date,
        string $workStart = '00:00:00',
        string $workEnd = '23:59:59'
    ): bool {
        $workStartDateTime = "{$date} {$workStart}";
        $workEndDateTime = "{$date} {$workEnd}";

        // Check if there are blocked periods that collectively cover the entire working day
        $blockingPeriods = static::where('start_datetime', '<=', $workStartDateTime)
                                 ->where('end_datetime', '>=', $workEndDateTime)
                                 ->exists();

        return $blockingPeriods;
    }

    /**
     * CI3 compatibility method (with bug fix).
     *
     * Original CI3 had: num_rows() > 1 (bug)
     * Fixed version: exists() which is equivalent to num_rows() > 0
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return bool
     * @deprecated Use isDateBlocked() or isEntireDateBlocked() instead
     */
    // public static function is_entire_date_blocked(string $date): bool
    // {
    //     // This maintains the CI3 method name but fixes the bug
    //     return static::isDateBlocked($date);
    // }
}
