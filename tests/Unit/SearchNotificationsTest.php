<?php

namespace Tests\Unit;

use App\Models\UserSearch;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\GeoJson;
use App\Support\Warehouse\SearchNotifications;
use Carbon\Carbon;
use Tests\TestCase;

class SearchNotificationsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_never_skips_matching(): void
    {
        $result = (new SearchNotifications)->forSearches('never', collect([$this->search()]));

        $this->assertSame([], $result['features']);
        $this->assertSame('never', $result['frequency']);
        $this->assertNull($result['since']);
    }

    public function test_alert_filter_drops_saved_map_dates_and_uses_created_at(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');

        $search = $this->search([
            'filter' => [
                'date' => ['type' => 'last 365 days'],
                'submitted_from' => '2024-01-01',
                'app' => [2],
            ],
        ]);
        $since = Carbon::parse('2026-08-13 12:00:00');

        $input = (new SearchNotifications)->alertFilterInput($search, $since);
        $filter = ApplicationFilter::fromArray($input, defaultDateWindow: false);

        $this->assertArrayNotHasKey('date', $input);
        $this->assertArrayNotHasKey('submitted_from', $input);
        $this->assertNull($filter->submittedFrom);
        $this->assertSame(-33.86, $filter->centerLat);
        $this->assertSame(151.21, $filter->centerLng);
        $this->assertSame(500, $filter->radiusMeters);
        $this->assertSame([2], $filter->applicationClassIds);
        $this->assertSame('2026-08-13 12:00:00', $filter->createdFrom?->format('Y-m-d H:i:s'));
        $this->assertFalse($filter->createdFromExclusive);
    }

    public function test_last_notified_at_is_exclusive_created_at_bound(): void
    {
        $search = $this->search();
        $search->last_notified_at = Carbon::parse('2026-08-19 08:00:00');
        $since = Carbon::parse('2026-08-19 08:00:00');

        $input = (new SearchNotifications)->alertFilterInput($search, $since);
        $filter = ApplicationFilter::fromArray($input, defaultDateWindow: false);

        $this->assertTrue($filter->createdFromExclusive);
        $this->assertSame('2026-08-19 08:00:00', $filter->createdFrom?->format('Y-m-d H:i:s'));
    }

    public function test_frequency_lookback_when_never_notified(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');

        $service = new SearchNotifications;

        $this->assertSame(
            '2026-08-13 12:00:00',
            $service->ingestedSince('weekly', null)?->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-08-19 08:00:00',
            $service->ingestedSince('weekly', '2026-08-19 08:00:00')?->format('Y-m-d H:i:s'),
        );
        $this->assertNull($service->ingestedSince('never', null));
    }

    public function test_geojson_includes_search_and_created_at(): void
    {
        $collection = GeoJson::featureCollection([(object) [
            'id' => 99,
            'lat' => -33.86,
            'lng' => 151.21,
            'created_at' => '2026-08-19 10:00:00',
            'search_id' => 7,
            'search_name' => 'Home',
            'formatted_address' => '1 Test St, Sydney NSW',
        ]]);

        $this->assertSame(99, $collection['features'][0]['properties']['id']);
        $this->assertSame(7, $collection['features'][0]['properties']['search_id']);
        $this->assertSame('Home', $collection['features'][0]['properties']['search_name']);
        $this->assertNotNull($collection['features'][0]['properties']['created_at']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function search(array $overrides = []): UserSearch
    {
        $search = new UserSearch;
        $search->id = 7;
        $search->forceFill([
            'name' => 'Home',
            'lat' => -33.86,
            'lng' => 151.21,
            'radius' => 500,
            'filter' => ['date' => ['type' => 'last 365 days']],
            'notify' => true,
            ...$overrides,
        ]);

        return $search;
    }
}
