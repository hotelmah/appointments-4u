<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'ip',
        'type',
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
     * Accessors & Mutators
     */

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the consent age in days.
     */
    public function getAgeInDaysAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if consent is recent (within 30 days).
     */
    public function getIsRecentAttribute(): bool
    {
        return $this->created_at->greaterThan(now()->subDays(30));
    }

    /**
     * Get formatted consent date.
     */
    public function getConsentDateAttribute(): string
    {
        return $this->created_at->format('M d, Y g:i A');
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to consents by email.
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope a query to consents by IP address.
     */
    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }

    /**
     * Scope a query to consents by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to recent consents (within specified days).
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    /**
     * Scope a query to consents from today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope a query to consents from this week.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope a query to consents from this month.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }

    /**
     * Scope a query to consents within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Helper Methods
     */

    /**
     * Check if this consent matches a user's email.
     */
    public function matchesEmail(string $email): bool
    {
        return strtolower($this->email) === strtolower($email);
    }

    /**
     * Check if consent was given from a specific IP.
     */
    public function isFromIp(string $ip): bool
    {
        return $this->ip === $ip;
    }

    /**
     * Get all consent types available in the system.
     */
    public static function getConsentTypes(): array
    {
        return self::distinct('type')
            ->whereNotNull('type')
            ->pluck('type')
            ->toArray();
    }

    /**
     * Check if a user has given consent of a specific type.
     */
    public static function hasConsent(string $email, string $type): bool
    {
        return self::where('email', $email)
            ->where('type', $type)
            ->exists();
    }

    /**
     * Get the latest consent for an email and type.
     */
    public static function getLatestConsent(string $email, ?string $type = null)
    {
        $query = self::where('email', $email);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->latest()->first();
    }

    /**
     * Revoke consent by creating a withdrawal record.
     * Note: This creates a new record to maintain audit trail.
     */
    public static function revokeConsent(string $email, string $type, string $ip = null): self
    {
        return self::create([
            'email' => $email,
            'type' => $type . '_revoked',
            'ip' => $ip ?? request()->ip(),
            'first_name' => null,
            'last_name' => null,
        ]);
    }

    /**
     * Export consent data for a specific email (GDPR data export).
     */
    public static function exportForEmail(string $email): array
    {
        return self::where('email', $email)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($consent) {
                return [
                    'full_name' => $consent->full_name,
                    'email' => $consent->email,
                    'consent_type' => $consent->type,
                    'ip_address' => $consent->ip,
                    'consent_date' => $consent->consent_date,
                ];
            })
            ->toArray();
    }
}
