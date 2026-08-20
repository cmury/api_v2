<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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

    public const NOTIFICATION = 'notification';

    public const FAVOURITE_CREATED = 'favourite_created';

    public const FAVOURITE_UPDATED = 'favourite_updated';

    public const FAVOURITE_DELETED = 'favourite_deleted';

    public const CONTACT_CONTRIBUTED = 'contact_contributed';

    public const APPLICATION_CLAIMED = 'application_claimed';

    public const APPLICATION_UNCLAIMED = 'application_unclaimed';

    /** Viewed an application detail (legacy action name: application). */
    public const APPLICATION_VIEWED = 'application';

    /** Shared an application link (list row, detail chrome, etc.). */
    public const APPLICATION_SHARED = 'application_shared';

    /** Shared the current search / map parameters. */
    public const SEARCH_SHARED = 'search_shared';

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
        ?User $user,
        string $action,
        ?array $payload = null,
        ?Model $actionable = null,
    ): UserLog {
        return UserLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'payload' => $payload,
            'actionable_type' => $actionable?->getMorphClass(),
            'actionable_id' => $actionable?->getKey(),
        ]);
    }

    /**
     * Log once per user/action/actionable (and optional visitor scope) within a short window.
     * Avoids duplicate rows from React Strict Mode remounts and rapid refetches.
     *
     * For anonymous traffic, pass $onceScope (e.g. IP+UA fingerprint) so guests do not
     * collapse into a single global "one view per minute" bucket.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function logOnce(
        ?User $user,
        string $action,
        ?array $payload = null,
        ?Model $actionable = null,
        int $withinSeconds = 60,
        ?string $onceScope = null,
    ): ?UserLog {
        $withinSeconds = max(1, $withinSeconds);
        $scope = is_string($onceScope) && $onceScope !== '' ? $onceScope : null;
        $visitor = $scope !== null ? $this->visitorToken($scope) : null;

        if ($visitor !== null) {
            $payload = array_merge($payload ?? [], [
                'visitor' => $visitor,
            ]);
        }

        $cacheKey = sprintf(
            'user-log-once:%s:%s:%s:%s:%s',
            $user?->id ?? 'anon',
            $action,
            $actionable?->getMorphClass() ?? '-',
            (string) ($actionable?->getKey() ?? '-'),
            $visitor ?? '-',
        );

        // Atomic gate so concurrent duplicate requests (e.g. Strict Mode) only log once.
        if (! Cache::add($cacheKey, true, $withinSeconds)) {
            return null;
        }

        $query = UserLog::query()
            ->where('action', $action)
            ->where('created_at', '>=', now()->subSeconds($withinSeconds));

        if ($user !== null) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id');
        }

        if ($actionable !== null) {
            $query
                ->where('actionable_type', $actionable->getMorphClass())
                ->where('actionable_id', $actionable->getKey());
        } else {
            $query->whereNull('actionable_type')->whereNull('actionable_id');
        }

        if ($visitor !== null) {
            $query->where('payload->visitor', $visitor);
        }

        if ($query->exists()) {
            return null;
        }

        return $this->log($user, $action, $payload, $actionable);
    }

    /**
     * Record an application detail view for a signed-in user, or anonymously when enabled.
     *
     * @param  array<string, mixed>|null  $extraPayload
     */
    public function logApplicationView(
        ?User $user,
        Model $application,
        ?Request $request = null,
        ?array $extraPayload = null,
    ): ?UserLog {
        return $this->logPublicActivity(
            $user,
            self::APPLICATION_VIEWED,
            $request,
            array_merge(
                ['application_id' => $application->getKey()],
                $extraPayload ?? [],
            ),
            $application,
            config('imby.activity.log_anonymous_application_views', true),
        );
    }

    /**
     * Record that someone shared an application link.
     *
     * @param  array<string, mixed>|null  $extraPayload
     */
    public function logApplicationShare(
        ?User $user,
        Model $application,
        ?Request $request = null,
        ?array $extraPayload = null,
    ): ?UserLog {
        return $this->logPublicActivity(
            $user,
            self::APPLICATION_SHARED,
            $request,
            array_merge(
                ['application_id' => $application->getKey()],
                $extraPayload ?? [],
            ),
            $application,
            config('imby.activity.log_anonymous_shares', true),
        );
    }

    /**
     * Record that someone shared the current search / map URL.
     *
     * @param  array<string, mixed>|null  $extraPayload
     */
    public function logSearchShare(
        ?User $user,
        ?Request $request = null,
        ?array $extraPayload = null,
    ): ?UserLog {
        return $this->logPublicActivity(
            $user,
            self::SEARCH_SHARED,
            $request,
            $extraPayload ?? [],
            null,
            config('imby.activity.log_anonymous_shares', true),
        );
    }

    /**
     * Signed-in or anonymous (when enabled) activity with short-window de-dupe.
     *
     * @param  array<string, mixed>  $payload
     */
    private function logPublicActivity(
        ?User $user,
        string $action,
        ?Request $request,
        array $payload,
        ?Model $actionable,
        bool $allowAnonymous,
    ): ?UserLog {
        if ($user instanceof User) {
            return $this->logOnce(
                $user,
                $action,
                $payload,
                $actionable,
            );
        }

        if (! $allowAnonymous) {
            return null;
        }

        $fingerprint = $request instanceof Request
            ? $this->visitorFingerprint($request)
            : '';

        if ($fingerprint === '') {
            return null;
        }

        $bucket = $this->anonymousBucketUser();
        $anonymousPayload = array_merge($payload, ['anonymous' => true]);

        return $this->logOnce(
            $bucket,
            $action,
            $anonymousPayload,
            $actionable,
            onceScope: $fingerprint,
        );
    }

    public function visitorFingerprint(Request $request): string
    {
        return hash(
            'sha256',
            ($request->ip() ?? '').'|'.(string) $request->userAgent(),
        );
    }

    public function visitorToken(string $fingerprint): string
    {
        return substr(hash('sha256', $fingerprint), 0, 16);
    }

    private function anonymousBucketUser(): ?User
    {
        $id = config('imby.activity.anonymous_user_id');

        if (! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return User::query()->find((int) $id);
    }
}
