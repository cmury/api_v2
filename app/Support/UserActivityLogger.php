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
