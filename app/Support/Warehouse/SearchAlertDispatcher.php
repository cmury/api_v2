<?php

namespace App\Support\Warehouse;

use App\Mail\SearchAlertMail;
use App\Models\User;
use App\Models\UserSearch;
use App\Support\UserActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends saved-search alert digests and stamps last_notified_at after a successful
 * send, or when a due run has nothing new (so cadence does not retry hourly).
 */
class SearchAlertDispatcher
{
    public function __construct(
        private readonly SearchNotifications $notifications = new SearchNotifications,
        private readonly SearchAlertCadence $cadence = new SearchAlertCadence,
        private readonly SearchAlertDigest $digest = new SearchAlertDigest,
        private readonly UserActivityLogger $activityLogger = new UserActivityLogger,
    ) {}

    /**
     * @return array{sent: int, empty: int, skipped: int, failed: int}
     */
    public function run(bool $dryRun = false, ?int $userId = null): array
    {
        $counts = ['sent' => 0, 'empty' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($this->users($userId) as $user) {
            $outcome = $this->processUser($user, $dryRun);
            $counts[$outcome]++;
        }

        return $counts;
    }

    /**
     * @return 'sent'|'empty'|'skipped'|'failed'
     */
    public function processUser(User $user, bool $dryRun = false): string
    {
        $frequency = $this->cadence->frequencyFor($user);
        if ($frequency === 'never') {
            return 'skipped';
        }

        /** @var Collection<int, UserSearch> $searches */
        $searches = $user->searches
            ->where('notify', true)
            ->values();

        if ($searches->isEmpty()) {
            return 'skipped';
        }

        $due = $searches->filter(
            fn (UserSearch $search): bool => $this->cadence->isDue($frequency, $search->last_notified_at),
        );

        if ($due->isEmpty()) {
            return 'skipped';
        }

        $geojson = $this->notifications->forSearches($frequency, $due);
        $sections = $this->digest->fromGeoJson($geojson);
        $total = $this->digest->totalApplications($sections);

        if ($total === 0) {
            if (! $dryRun) {
                $this->stamp($due);
            }

            return 'empty';
        }

        if ($dryRun) {
            return 'sent';
        }

        try {
            Mail::to($user->email)->send(new SearchAlertMail($user, $sections, $total));
        } catch (\Throwable $e) {
            Log::error('Search alert mail failed.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return 'failed';
        }

        $this->stamp($due);

        try {
            $this->activityLogger->log(
                $user,
                UserActivityLogger::NOTIFICATION,
                [
                    'search_ids' => $due->pluck('id')->values()->all(),
                    'count' => $total,
                ],
            );
        } catch (\Throwable) {
            // Mail already went out; logging must not roll back the watermark.
        }

        return 'sent';
    }

    /**
     * @return Collection<int, User>
     */
    private function users(?int $userId): Collection
    {
        $query = User::query()
            ->whereHas('searches', fn ($q) => $q->where('notify', true))
            ->with([
                'preferences',
                'searches' => fn ($q) => $q->where('notify', true)->orderBy('name'),
            ])
            ->orderBy('id');

        if ($userId !== null) {
            $query->whereKey($userId);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, UserSearch>  $searches
     */
    private function stamp(Collection $searches): void
    {
        $now = now();

        foreach ($searches as $search) {
            $search->last_notified_at = $now;
            $search->save();
        }
    }
}
