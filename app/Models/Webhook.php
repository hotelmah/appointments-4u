<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class Webhook extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'url',
        'actions',
        'secret_header',
        'secret_token',
        'is_ssl_verified',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'secret_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'actions' => 'array',
        'is_ssl_verified' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'secret_header' => 'X-Ea-Token',
        'is_ssl_verified' => true,
    ];

    /**
     * Webhook action constants.
     */
    public const ACTION_APPOINTMENT_SAVED = 'appointment_saved';
    public const ACTION_APPOINTMENT_DELETED = 'appointment_deleted';
    public const ACTION_UNAVAILABILITY_SAVED = 'unavailability_saved';
    public const ACTION_UNAVAILABILITY_DELETED = 'unavailability_deleted';
    public const ACTION_CUSTOMER_SAVED = 'customer_saved';
    public const ACTION_CUSTOMER_DELETED = 'customer_deleted';
    public const ACTION_SERVICE_SAVED = 'service_saved';
    public const ACTION_SERVICE_DELETED = 'service_deleted';
    public const ACTION_CATEGORY_SAVED = 'category_saved';
    public const ACTION_CATEGORY_DELETED = 'category_deleted';
    public const ACTION_PROVIDER_SAVED = 'provider_saved';
    public const ACTION_PROVIDER_DELETED = 'provider_deleted';
    public const ACTION_SECRETARY_SAVED = 'secretary_saved';
    public const ACTION_SECRETARY_DELETED = 'secretary_deleted';
    public const ACTION_ADMIN_SAVED = 'admin_saved';
    public const ACTION_ADMIN_DELETED = 'admin_deleted';

    /**
     * Get all available webhook actions.
     *
     * @return array
     */
    public static function getAvailableActions(): array
    {
        return [
            self::ACTION_APPOINTMENT_SAVED,
            self::ACTION_APPOINTMENT_DELETED,
            self::ACTION_UNAVAILABILITY_SAVED,
            self::ACTION_UNAVAILABILITY_DELETED,
            self::ACTION_CUSTOMER_SAVED,
            self::ACTION_CUSTOMER_DELETED,
            self::ACTION_SERVICE_SAVED,
            self::ACTION_SERVICE_DELETED,
            self::ACTION_CATEGORY_SAVED,
            self::ACTION_CATEGORY_DELETED,
            self::ACTION_PROVIDER_SAVED,
            self::ACTION_PROVIDER_DELETED,
            self::ACTION_SECRETARY_SAVED,
            self::ACTION_SECRETARY_DELETED,
            self::ACTION_ADMIN_SAVED,
            self::ACTION_ADMIN_DELETED,
        ];
    }

    /**
     * Relationships
     */
    // Webhook doesn't have direct relationships with other models

    /**
     * Accessors & Mutators
     */

    /**
     * Get the display name for the webhook.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: 'Webhook #' . $this->id;
    }

    /**
     * Get the masked secret token (show only last 4 characters).
     */
    public function getMaskedSecretTokenAttribute(): string
    {
        if (!$this->secret_token) {
            return 'Not set';
        }

        if (strlen($this->secret_token) <= 4) {
            return str_repeat('*', strlen($this->secret_token));
        }

        return str_repeat('*', strlen($this->secret_token) - 4) . substr($this->secret_token, -4);
    }

    /**
     * Get the domain from the webhook URL.
     */
    public function getDomainAttribute(): ?string
    {
        if (!$this->url) {
            return null;
        }

        $parsedUrl = parse_url($this->url);
        return $parsedUrl['host'] ?? null;
    }

    /**
     * Check if the webhook URL uses HTTPS.
     */
    public function getIsSecureAttribute(): bool
    {
        if (!$this->url) {
            return false;
        }

        return str_starts_with(strtolower($this->url), 'https://');
    }

    /**
     * Get the count of configured actions.
     */
    public function getActionsCountAttribute(): int
    {
        return is_array($this->actions) ? count($this->actions) : 0;
    }

    /**
     * Check if webhook has any actions configured.
     */
    public function getHasActionsAttribute(): bool
    {
        return $this->actions_count > 0;
    }

    /**
     * Check if webhook is fully configured and ready to use.
     */
    public function getIsConfiguredAttribute(): bool
    {
        return !empty($this->url)
            && !empty($this->actions)
            && is_array($this->actions)
            && count($this->actions) > 0;
    }

    /**
     * Get human-readable action names.
     */
    public function getActionNamesAttribute(): array
    {
        if (!is_array($this->actions)) {
            return [];
        }

        return array_map(function ($action) {
            return ucwords(str_replace('_', ' ', $action));
        }, $this->actions);
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to only include active/configured webhooks.
     */
    public function scopeConfigured($query)
    {
        return $query->whereNotNull('url')
            ->whereNotNull('actions')
            ->whereJsonLength('actions', '>', 0);
    }

    /**
     * Scope a query to webhooks that handle a specific action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->whereJsonContains('actions', $action);
    }

    /**
     * Scope a query to webhooks with SSL verification enabled.
     */
    public function scopeSslVerified($query)
    {
        return $query->where('is_ssl_verified', true);
    }

    /**
     * Scope a query to webhooks with SSL verification disabled.
     */
    public function scopeSslNotVerified($query)
    {
        return $query->where('is_ssl_verified', false);
    }

    /**
     * Scope a query to search webhooks by name or URL.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('url', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope a query to webhooks by domain.
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('url', 'like', "%{$domain}%");
    }

    /**
     * Helper Methods
     */

    /**
     * Generate a new random secret token.
     *
     * @param int $length
     * @return string
     */
    public static function generateSecretToken(int $length = 64): string
    {
        return Str::random($length);
    }

    /**
     * Regenerate the secret token for this webhook.
     *
     * @param int $length
     * @return bool
     */
    public function regenerateSecretToken(int $length = 64): bool
    {
        $this->secret_token = self::generateSecretToken($length);
        return $this->save();
    }

    /**
     * Check if this webhook handles a specific action.
     *
     * @param string $action
     * @return bool
     */
    public function handlesAction(string $action): bool
    {
        return is_array($this->actions) && in_array($action, $this->actions);
    }

    /**
     * Add an action to the webhook.
     *
     * @param string $action
     * @return bool
     */
    public function addAction(string $action): bool
    {
        if (!in_array($action, self::getAvailableActions())) {
            return false;
        }

        $actions = is_array($this->actions) ? $this->actions : [];

        if (!in_array($action, $actions)) {
            $actions[] = $action;
            $this->actions = $actions;
            return $this->save();
        }

        return false; // Action already exists
    }

    /**
     * Remove an action from the webhook.
     *
     * @param string $action
     * @return bool
     */
    public function removeAction(string $action): bool
    {
        if (!is_array($this->actions)) {
            return false;
        }

        $actions = $this->actions;
        $key = array_search($action, $actions);

        if ($key !== false) {
            unset($actions[$key]);
            $this->actions = array_values($actions); // Re-index array
            return $this->save();
        }

        return false;
    }

    /**
     * Set multiple actions at once.
     *
     * @param array $actions
     * @return bool
     */
    public function setActions(array $actions): bool
    {
        $validActions = array_intersect($actions, self::getAvailableActions());
        $this->actions = array_values(array_unique($validActions));
        return $this->save();
    }

    /**
     * Clear all actions.
     *
     * @return bool
     */
    public function clearActions(): bool
    {
        $this->actions = [];
        return $this->save();
    }

    /**
     * Validate the webhook URL.
     *
     * @return bool
     */
    public function hasValidUrl(): bool
    {
        if (!$this->url) {
            return false;
        }

        return filter_var($this->url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Test the webhook by sending a test payload.
     *
     * @param array $testPayload
     * @return array Response with 'success' boolean and 'message' string
     */
    public function test(array $testPayload = []): array
    {
        if (!$this->hasValidUrl()) {
            return [
                'success' => false,
                'message' => 'Invalid webhook URL',
                'status_code' => null,
            ];
        }

        $payload = array_merge([
            'event' => 'test',
            'timestamp' => now()->toIso8601String(),
        ], $testPayload);

        try {
            $response = $this->sendRequest($payload);

            return [
                'success' => $response['success'],
                'message' => $response['success'] ? 'Webhook test successful' : 'Webhook test failed',
                'status_code' => $response['status_code'],
                'response_body' => $response['body'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => null,
            ];
        }
    }

    /**
     * Trigger the webhook with a payload.
     *
     * @param string $action
     * @param array $data
     * @return array Response with 'success' boolean and 'status_code' integer
     */
    public function trigger(string $action, array $data): array
    {
        if (!$this->handlesAction($action)) {
            return [
                'success' => false,
                'message' => 'Webhook does not handle this action',
                'status_code' => null,
            ];
        }

        if (!$this->hasValidUrl()) {
            return [
                'success' => false,
                'message' => 'Invalid webhook URL',
                'status_code' => null,
            ];
        }

        $payload = [
            'event' => $action,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        try {
            return $this->sendRequest($payload);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => null,
            ];
        }
    }

    /**
     * Send HTTP request to the webhook URL.
     *
     * @param array $payload
     * @return array
     */
    protected function sendRequest(array $payload): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->secret_token) {
            $headers[$this->secret_header] = $this->secret_token;
        }

        $request = Http::withHeaders($headers)
            ->timeout(30);

        if (!$this->is_ssl_verified) {
            $request->withoutVerifying();
        }

        $response = $request->post($this->url, $payload);

        return [
            'success' => $response->successful(),
            'status_code' => $response->status(),
            'body' => $response->body(),
        ];
    }

    /**
     * Get webhook statistics (if logging is implemented).
     *
     * @return array
     */
    public function getStats(): array
    {
        // This is a placeholder for future implementation
        // You would typically track webhook calls in a separate logs table
        return [
            'total_calls' => 0,
            'successful_calls' => 0,
            'failed_calls' => 0,
            'last_called_at' => null,
        ];
    }

    /**
     * Clone this webhook with a new name.
     *
     * @param string $newName
     * @return self
     */
    public function duplicate(string $newName): self
    {
        $clone = $this->replicate();
        $clone->name = $newName;
        $clone->secret_token = self::generateSecretToken();
        $clone->save();

        return $clone;
    }

    /**
     * Check if webhook can be deleted safely.
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        // Add any business logic here
        // For example, check if webhook has pending logs, etc.
        return true;
    }

    /**
     * Business Logic Helpers
     */

    /**
     * Trigger webhook for all configured webhooks that handle the action.
     *
     * @param string $action
     * @param array $data
     * @return array Results from all triggered webhooks
     */
    public static function triggerAll(string $action, array $data): array
    {
        $webhooks = self::configured()
            ->forAction($action)
            ->get();

        $results = [];

        foreach ($webhooks as $webhook) {
            $results[$webhook->id] = $webhook->trigger($action, $data);
        }

        return $results;
    }

    /**
     * Get webhook configuration summary.
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->display_name,
            'url' => $this->url,
            'domain' => $this->domain,
            'is_secure' => $this->is_secure,
            'is_ssl_verified' => $this->is_ssl_verified,
            'actions_count' => $this->actions_count,
            'actions' => $this->action_names,
            'is_configured' => $this->is_configured,
            'has_secret' => !empty($this->secret_token),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Validate webhook configuration before saving.
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->url)) {
            $errors[] = 'URL is required';
        } elseif (!$this->hasValidUrl()) {
            $errors[] = 'URL is not valid';
        }

        if (empty($this->actions) || !is_array($this->actions)) {
            $errors[] = 'At least one action must be configured';
        } else {
            $invalidActions = array_diff($this->actions, self::getAvailableActions());
            if (!empty($invalidActions)) {
                $errors[] = 'Invalid actions: ' . implode(', ', $invalidActions);
            }
        }

        if (empty($this->secret_header)) {
            $errors[] = 'Secret header name is required';
        }

        return $errors;
    }
}
