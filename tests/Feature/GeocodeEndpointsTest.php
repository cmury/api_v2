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
            ->assertJsonPath('data.0.label', 'Balmain, Inner West Council NSW 2041')
            ->assertJsonPath('data.0.lat', -33.858)
            ->assertJsonPath('data.0.lng', 151.178)
            ->assertJsonPath('data.0.source', 'nominatim');
    }

    public function test_geocode_search_includes_street_in_label(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '-33.8593115',
                    'lon' => '151.1822048',
                    'display_name' => 'Booth Street, Balmain, Inner West, Sydney, New South Wales, 2041, Australia',
                    'address' => [
                        'road' => 'Booth Street',
                        'suburb' => 'Balmain',
                        'borough' => 'Inner West',
                        'city' => 'Sydney',
                        'state' => 'New South Wales',
                        'postcode' => '2041',
                    ],
                ],
                [
                    'lat' => '-33.8615467',
                    'lon' => '151.1824849',
                    'display_name' => 'Booth Street, Balmain, Inner West, Sydney, New South Wales, 2041, Australia',
                    'address' => [
                        'road' => 'Booth Street',
                        'suburb' => 'Balmain',
                        'borough' => 'Inner West',
                        'city' => 'Sydney',
                        'state' => 'New South Wales',
                        'postcode' => '2041',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q='.urlencode('8 Booth Street Balmain'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Booth Street, Balmain NSW 2041')
            ->assertJsonPath('data.0.lga', 'Inner West');
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
            ->assertJsonPath('data.label', 'Balmain, Inner West Council NSW 2041');
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
