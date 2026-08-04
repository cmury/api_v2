<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_geocode_search_requires_query(): void
    {
        $this->getJson('/api/geocode')
            ->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }

    public function test_geocode_search_returns_mapped_results(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '-33.8580000',
                    'lon' => '151.1780000',
                    'display_name' => 'Balmain, Inner West Council, New South Wales, Australia',
                    'address' => [
                        'suburb' => 'Balmain',
                        'municipality' => 'Inner West Council',
                        'state' => 'New South Wales',
                        'postcode' => '2041',
                    ],
                    'boundingbox' => ['-33.87', '-33.84', '151.16', '151.19'],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=balmain')
            ->assertOk()
            ->assertJsonPath('data.0.suburb', 'Balmain')
            ->assertJsonPath('data.0.lat', -33.858)
            ->assertJsonPath('data.0.lng', 151.178)
            ->assertJsonPath('data.0.source', 'nominatim');
    }

    public function test_geocode_reverse_requires_coordinates(): void
    {
        $this->getJson('/api/geocode/reverse')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_geocode_reverse_returns_place(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'lat' => '-33.8580000',
                'lon' => '151.1780000',
                'display_name' => 'Balmain, Inner West Council, New South Wales, Australia',
                'address' => [
                    'suburb' => 'Balmain',
                    'municipality' => 'Inner West Council',
                    'state' => 'New South Wales',
                    'postcode' => '2041',
                ],
            ]),
        ]);

        $this->getJson('/api/geocode/reverse?lat=-33.858&lng=151.178')
            ->assertOk()
            ->assertJsonPath('data.suburb', 'Balmain')
            ->assertJsonPath('data.label', 'Balmain, Inner West Council, New South Wales');
    }

    public function test_geocode_reverse_returns_null_data_when_not_found(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'error' => 'Unable to geocode',
            ]),
        ]);

        $this->getJson('/api/geocode/reverse?lat=-10.12345&lng=131.12345')
            ->assertOk()
            ->assertJson(['data' => null]);
    }
}
