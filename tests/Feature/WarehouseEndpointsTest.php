<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseEndpointsTest extends TestCase
{
    public function test_map_markers_is_public_but_validates_bounds_size(): void
    {
        $this->getJson('/api/map/markers?'.http_build_query([
            'query' => json_encode(['map' => ['bounds' => [1, 2]]]),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('map.bounds');
    }

    public function test_authorities_require_authentication(): void
    {
        $this->getJson('/api/authorities')->assertUnauthorized();
    }

    public function test_applications_require_authentication(): void
    {
        $this->getJson('/api/applications')->assertUnauthorized();
    }

    public function test_locations_require_authentication(): void
    {
        $this->getJson('/api/locations')->assertUnauthorized();
    }

    public function test_stats_require_authentication_and_metric(): void
    {
        $this->getJson('/api/stats')->assertUnauthorized();

        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->getJson('/api/stats')
            ->assertStatus(422)
            ->assertJsonValidationErrors('metric');
    }

    public function test_stats_rejects_unknown_metric(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->getJson('/api/stats?metric=not_a_metric')
            ->assertStatus(422)
            ->assertJsonValidationErrors('metric');
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_taxonomies_require_authentication(): void
    {
        $this->getJson('/api/taxonomies/application-classes')->assertUnauthorized();
    }

    public function test_map_csv_requires_authentication(): void
    {
        $this->getJson('/api/map/markers/csv')->assertUnauthorized();
    }

    public function test_charts_reject_invalid_format(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->getJson('/api/charts?metric=applications&format=not_a_format')
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');
    }

    public function test_charts_accept_categorical_metric_validation(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $response = $this->getJson('/api/charts?metric=application_types&format=categorical&limit=5');
        $this->assertNotSame(422, $response->status());
        $this->assertNotSame(401, $response->status());
    }

    public function test_authority_statistics_require_authentication(): void
    {
        $this->getJson('/api/authorities/1/statistics')->assertUnauthorized();
    }

    public function test_authorities_statistics_index_requires_authentication(): void
    {
        $this->getJson('/api/authorities/statistics')->assertUnauthorized();
    }

    public function test_authority_locations_require_authentication(): void
    {
        $this->getJson('/api/authorities/1/locations')->assertUnauthorized();
    }

    public function test_authority_boundary_requires_authentication(): void
    {
        $this->getJson('/api/authorities/1/boundary')->assertUnauthorized();
    }

    public function test_taxonomy_types_require_authentication(): void
    {
        $this->getJson('/api/taxonomies/application-types')->assertUnauthorized();
        $this->getJson('/api/taxonomies/development-types')->assertUnauthorized();
        $this->getJson('/api/taxonomies/decision-types')->assertUnauthorized();
    }

    public function test_legislation_endpoints_require_authentication(): void
    {
        $this->getJson('/api/legislation')->assertUnauthorized();
        $this->getJson('/api/legislation/1')->assertUnauthorized();
        $this->getJson('/api/legislation/1/applications')->assertUnauthorized();
    }

    public function test_application_legislation_requires_authentication(): void
    {
        $this->getJson('/api/applications/1/legislation')->assertUnauthorized();
    }

    public function test_forecasts_require_authentication(): void
    {
        $this->getJson('/api/forecasts')->assertUnauthorized();
    }

    public function test_forecasts_reject_unknown_group_by(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->getJson('/api/forecasts?group_by=galaxy')
            ->assertStatus(422)
            ->assertJsonValidationErrors('group_by');
    }
}
