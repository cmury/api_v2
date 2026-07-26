<?php

namespace App\Support\Warehouse;

use App\Models\User;
use App\Models\UserSearch;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds GeoJSON of recent applications matching the user's notify-enabled searches.
 */
final class SearchNotifications
{
    public function __construct(
        private readonly MapMarkerQuery $mapMarkerQuery = new MapMarkerQuery,
    ) {}

    /**
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        $frequency = $user->preferences?->new_application_email_frequency ?? 'daily';
        $days = match ($frequency) {
            'weekly' => 7,
            'fortnightly' => 15,
            'monthly' => 30,
            default => 1,
        };

        $from = Carbon::now()->subDays($days)->startOfDay();
        $to = Carbon::now()->endOfDay();

        /** @var Collection<int, UserSearch> $searches */
        $searches = UserSearch::query()
            ->where('user_id', $user->id)
            ->where('notify', true)
            ->get();

        $byLocation = collect();

        foreach ($searches as $search) {
            $filterInput = is_array($search->filter) ? $search->filter : [];
            $filterInput['lat'] = $search->lat;
            $filterInput['lng'] = $search->lng;
            $filterInput['radius'] = $search->radius;
            $filterInput['submitted_from'] = $from->toDateString();
            $filterInput['submitted_to'] = $to->toDateString();

            $filter = ApplicationFilter::fromArray($filterInput, defaultDateWindow: false);
            $rows = $this->mapMarkerQuery->search($filter);

            foreach ($rows as $row) {
                $byLocation[(int) $row->id] = $row;
            }
        }

        return GeoJson::featureCollection($byLocation->values());
    }
}
