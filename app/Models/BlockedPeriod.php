<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
     * Check if the blocked period dates are valid (end after start).
     */
    public function hasValidDates(): bool
    {
        return $this->start_datetime && $this->end_datetime
            && $this->end_datetime->greaterThan($this->start_datetime);
    }
}
