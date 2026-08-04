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

            if (Schema::connection($connection)->hasTable('users_favourites')) {
                DB::connection($connection)
                    ->table('users_favourites')
                    ->where('user_id', $userId)
                    ->delete();
            }

            // Null out contact contributions so application_contacts rows remain.
            if (Schema::connection($connection)->hasTable('application_contacts')) {
                DB::connection($connection)
                    ->table('application_contacts')
                    ->where('contributed_by_user_id', $userId)
                    ->update(['contributed_by_user_id' => null]);
            }

            if (Schema::connection($connection)->hasTable('contacts')) {
                $owned = DB::connection($connection)
                    ->table('contacts')
                    ->where('payload->owner_user_id', $userId)
                    ->get(['id', 'payload']);

                foreach ($owned as $row) {
                    $payload = is_string($row->payload)
                        ? json_decode($row->payload, true)
                        : (array) $row->payload;
                    unset($payload['owner_user_id']);
                    DB::connection($connection)
                        ->table('contacts')
                        ->where('id', $row->id)
                        ->update(['payload' => json_encode($payload ?: null)]);
                }
            }

            // Activity log FK is nullOnDelete; delete rows so nothing remains.
            UserLog::query()->where('user_id', $userId)->delete();

            // Insights chat (messages cascade from threads when FK is present).
            $threadsTable = ProductChatTables::threads();
            $messagesTable = ProductChatTables::messages();

            if (Schema::connection($connection)->hasTable($threadsTable)) {
                if (Schema::connection($connection)->hasTable($messagesTable)) {
                    $threadIds = DB::connection($connection)
                        ->table($threadsTable)
                        ->where('user_id', $userId)
                        ->pluck('id');

                    if ($threadIds->isNotEmpty()) {
                        DB::connection($connection)
                            ->table($messagesTable)
                            ->whereIn('thread_id', $threadIds)
                            ->delete();
                    }
                }

                DB::connection($connection)
                    ->table($threadsTable)
                    ->where('user_id', $userId)
                    ->delete();
            }

            if (Schema::connection($connection)->hasTable('users_subscriptions')) {
                if (Schema::connection($connection)->hasTable('users_subscription_items')) {
                    $subscriptionIds = DB::connection($connection)
                        ->table('users_subscriptions')
                        ->where('user_id', $userId)
                        ->pluck('id');

                    if ($subscriptionIds->isNotEmpty()) {
                        DB::connection($connection)
                            ->table('users_subscription_items')
                            ->whereIn('subscription_id', $subscriptionIds)
                            ->delete();
                    }
                }

                DB::connection($connection)
                    ->table('users_subscriptions')
                    ->where('user_id', $userId)
                    ->delete();
            }

            if ($user->hasStripeId()) {
                try {
                    $user->stripe()->customers->delete($user->stripe_id);
                } catch (\Throwable $e) {
                    report($e);
                }
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
