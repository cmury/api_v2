<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class UserActivityLogger
{
    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

    public const PASSKEY_LOGIN = 'passkey_login';

    public const PASSKEY_REGISTERED = 'passkey_registered';

    public const PASSKEY_DELETED = 'passkey_deleted';

    public const PASSWORD_CHANGED = 'password_changed';

    public const PASSWORD_RESET = 'password_reset';

    public const PROFILE_UPDATED = 'profile_updated';

    public const SETTINGS_UPDATED = 'settings_updated';

    public const SEARCH_CREATED = 'search_created';

    public const SEARCH_UPDATED = 'search_updated';

    public const SEARCH_DELETED = 'search_deleted';

    public const FAVOURITE_CREATED = 'favourite_created';

    public const FAVOURITE_UPDATED = 'favourite_updated';

    public const FAVOURITE_DELETED = 'favourite_deleted';

    public const CONTACT_CONTRIBUTED = 'contact_contributed';

    public const APPLICATION_CLAIMED = 'application_claimed';

    public const APPLICATION_UNCLAIMED = 'application_unclaimed';

    /** Viewed an application detail (legacy action name: application). */
    public const APPLICATION_VIEWED = 'application';

    public const MAP_CSV_EXPORTED = 'map_csv_exported';

    public const PORTFOLIO_ITEM_ADDED = 'portfolio_item_added';

    public const PORTFOLIO_ITEM_REMOVED = 'portfolio_item_removed';

    public const BILLING_CHECKOUT_STARTED = 'billing_checkout_started';

    public const BILLING_PORTAL_OPENED = 'billing_portal_opened';

    public const BILLING_PLAN_CHANGED = 'plan_changed';

    public const BILLING_CANCELED = 'billing_canceled';

    public const BILLING_RESUMED = 'billing_resumed';

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function log(
        User $user,
        string $action,
        ?array $payload = null,
        ?Model $actionable = null,
    ): UserLog {
        return UserLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'payload' => $payload,
            'actionable_type' => $actionable?->getMorphClass(),
            'actionable_id' => $actionable?->getKey(),
        ]);
    }

    /**
     * Log once per user/action/actionable within a short window.
     * Avoids duplicate rows from React Strict Mode remounts and rapid refetches.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function logOnce(
        User $user,
        string $action,
        ?array $payload = null,
        ?Model $actionable = null,
        int $withinSeconds = 60,
    ): ?UserLog {
        $withinSeconds = max(1, $withinSeconds);
        $cacheKey = sprintf(
            'user-log-once:%d:%s:%s:%s',
            $user->id,
            $action,
            $actionable?->getMorphClass() ?? '-',
            (string) ($actionable?->getKey() ?? '-'),
        );

        // Atomic gate so concurrent duplicate requests (e.g. Strict Mode) only log once.
        if (! Cache::add($cacheKey, true, $withinSeconds)) {
            return null;
        }

        $query = UserLog::query()
            ->where('user_id', $user->id)
            ->where('action', $action)
            ->where('created_at', '>=', now()->subSeconds($withinSeconds));

        if ($actionable !== null) {
            $query
                ->where('actionable_type', $actionable->getMorphClass())
                ->where('actionable_id', $actionable->getKey());
        } else {
            $query->whereNull('actionable_type')->whereNull('actionable_id');
        }

        if ($query->exists()) {
            return null;
        }

        return $this->log($user, $action, $payload, $actionable);
    }
}
