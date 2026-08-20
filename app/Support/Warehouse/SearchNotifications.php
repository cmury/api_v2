<?php

namespace App\Support\Warehouse;

use App\Models\User;
use App\Models\UserSearch;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * New-to-IMBY applications matching the user's notify-enabled searches.
 *
 * Saved map dates are ignored (those are an Explore window, not an alert
 * subscription). Frequency is the send cadence; the lookback is
 * last_notified_at when set, otherwise the frequency period on created_at.
 */
class SearchNotifications
{
    public function __construct(
        private readonly MapMarkerQuery $mapMarkerQuery = new MapMarkerQuery,
    ) {}

    /**
     * @return array{type: string, features: list<array<string, mixed>>, frequency: string, since: string|null}
     */
    public function forUser(User $user): array
    {
        $user->loadMissing('preferences');

        $frequency = $user->preferences?->notification_frequency
            ?? config('imby.default_notification_frequency', 'weekly');

        /** @var Collection<int, UserSearch> $searches */
        $searches = UserSearch::query()
            ->where('user_id', $user->id)
            ->where('notify', true)
            ->orderBy('name')
            ->get();

        return $this->forSearches($frequency, $searches);
    }

    /**
     * @param  Collection<int, UserSearch>  $searches
     * @return array{type: string, features: list<array<string, mixed>>, frequency: string, since: string|null}
     */
    public function forSearches(string $frequency, Collection $searches): array
    {
        $empty = [
            'type' => 'FeatureCollection',
            'features' => [],
            'frequency' => $frequency,
            'since' => null,
        ];

        if ($frequency === 'never') {
            return $empty;
        }

        $since = $this->ingestedSince($frequency, null);
        $rows = collect();

        foreach ($searches as $search) {
            $searchSince = $this->ingestedSince($frequency, $search->last_notified_at);
            if ($searchSince === null) {
                continue;
            }

            if ($since === null || $searchSince->lt($since)) {
                $since = $searchSince;
            }

            $filter = ApplicationFilter::fromArray(
                $this->alertFilterInput($search, $searchSince),
                defaultDateWindow: false,
            );
            $matched = $this->mapMarkerQuery->search($filter);

            foreach ($matched as $row) {
                $row->search_id = (int) $search->id;
                $row->search_name = $search->name;
                $rows->push($row);
            }
        }

        $sorted = $rows
            ->sortByDesc(fn (object $row): string => (string) ($row->created_at ?? ''))
            ->values();

        $collection = GeoJson::featureCollection($sorted);
        $collection['frequency'] = $frequency;
        $collection['since'] = $since?->toIso8601String();

        return $collection;
    }

    /**
     * Lower bound for applications.created_at. Null means do not query.
     */
    public function ingestedSince(string $frequency, mixed $lastNotifiedAt): ?CarbonInterface
    {
        if ($frequency === 'never') {
            return null;
        }

        if ($lastNotifiedAt !== null && $lastNotifiedAt !== '') {
            return Carbon::parse($lastNotifiedAt);
        }

        $days = match ($frequency) {
            'weekly' => 7,
            'fortnightly' => 15,
            'monthly' => 30,
            'immediately', 'daily' => 1,
            default => 1,
        };

        return Carbon::now()->subDays($days);
    }

    /**
     * Saved-search filters for alerts: keep place / type / cost, drop map dates.
     *
     * @return array<string, mixed>
     */
    public function alertFilterInput(UserSearch $search, CarbonInterface $since): array
    {
        $filterInput = is_array($search->filter) ? $search->filter : [];

        unset(
            $filterInput['date'],
            $filterInput['submitted_from'],
            $filterInput['submitted_to'],
        );

        $filterInput['lat'] = $search->lat;
        $filterInput['lng'] = $search->lng;
        $filterInput['radius'] = $search->radius;
        $filterInput['created_from'] = $since->toIso8601String();
        $filterInput['created_from_exclusive'] = $search->last_notified_at !== null;

        return $filterInput;
    }
}
