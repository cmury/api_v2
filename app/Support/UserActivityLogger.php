<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Model;

final class UserActivityLogger
{
    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

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

    public const PORTFOLIO_ITEM_ADDED = 'portfolio_item_added';

    public const PORTFOLIO_ITEM_REMOVED = 'portfolio_item_removed';

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
}
