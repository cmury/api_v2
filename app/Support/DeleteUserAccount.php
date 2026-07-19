<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLog;
use App\Models\UserPreference;
use App\Models\UserSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

final class DeleteUserAccount
{
    public function __invoke(User $user): void
    {
        $connection = DataDatabase::name();

        DB::connection($connection)->transaction(function () use ($user, $connection): void {
            $userId = $user->id;
            $email = $user->email;

            // Preferences reference searches — remove prefs first, then searches.
            UserPreference::query()->where('user_id', $userId)->delete();
            UserSearch::query()->where('user_id', $userId)->delete();

            // Activity log FK is nullOnDelete; delete rows so nothing remains.
            UserLog::query()->where('user_id', $userId)->delete();

            if (Schema::connection($connection)->hasTable('users_subscriptions')) {
                DB::connection($connection)
                    ->table('users_subscriptions')
                    ->where('user_id', $userId)
                    ->delete();
            }

            PersonalAccessToken::query()
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $userId)
                ->delete();

            if (Schema::connection($connection)->hasTable('password_reset_tokens')) {
                DB::connection($connection)
                    ->table('password_reset_tokens')
                    ->where('email', $email)
                    ->delete();
            }

            $user->delete();
        });
    }
}
