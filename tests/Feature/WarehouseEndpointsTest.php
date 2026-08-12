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

    public function test_application_show_is_public(): void
    {
        // Guest may hit missing warehouse schema (404/5xx); must not be 401.
        $this->assertNotSame(401, $this->getJson('/api/applications/1')->status());
    }

    public function test_application_view_beacon_is_public(): void
    {
        $this->assertNotSame(401, $this->postJson('/api/applications/1/view')->status());
    }

    public function test_location_applications_is_public(): void
    {
        $this->assertNotSame(401, $this->getJson('/api/locations/1/applications')->status());
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

    public function test_authority_applications_require_authentication(): void
    {
        $this->getJson('/api/authorities/1/applications')->assertUnauthorized();
    }

    public function test_authority_amalgamation_requires_authentication(): void
    {
        $this->getJson('/api/authorities/1/amalgamation')->assertUnauthorized();
    }

    public function test_authority_boundary_requires_authentication(): void
    {
        $this->getJson('/api/authorities/1/boundary')->assertUnauthorized();
    }

    public function test_applications_accept_type_id_filter_validation(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $response = $this->getJson('/api/applications?'.http_build_query([
            'application_type_ids' => [1],
            'development_type_ids' => [2],
            'decision_type_ids' => [3],
        ]));

        $this->assertNotSame(422, $response->status());
        $this->assertNotSame(401, $response->status());
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

    public function test_facilities_require_authentication(): void
    {
        $this->getJson('/api/facilities')->assertUnauthorized();
        $this->getJson('/api/facilities/1')->assertUnauthorized();
        $this->getJson('/api/facilities/1/applications')->assertUnauthorized();
        $this->getJson('/api/facilities/applications-near')->assertUnauthorized();
    }

    public function test_planning_controls_require_authentication(): void
    {
        $this->getJson('/api/planning-controls')->assertUnauthorized();
        $this->getJson('/api/planning-controls/1')->assertUnauthorized();
        $this->getJson('/api/taxonomies/planning-layers')->assertUnauthorized();
        $this->getJson('/api/taxonomies/planning-codes')->assertUnauthorized();
    }

    public function test_planning_controls_at_point_is_public_and_requires_coordinates(): void
    {
        $this->getJson('/api/planning-controls/at-point')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_facilities_applications_near_requires_identifier(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->getJson('/api/facilities/applications-near?radius=1000')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['facility_id', 'facility_search']);
    }

    public function test_applications_accept_source_filter_validation(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $response = $this->getJson('/api/applications?source=nsw-eplanning');
        $this->assertNotSame(422, $response->status());
        $this->assertNotSame(401, $response->status());
    }

    public function test_contacts_require_authentication(): void
    {
        $this->getJson('/api/contacts')->assertUnauthorized();
        $this->getJson('/api/contacts/1')->assertUnauthorized();
        $this->getJson('/api/contacts/1/applications')->assertUnauthorized();
        $this->getJson('/api/applications/1/contacts')->assertUnauthorized();
    }

    public function test_certifiers_require_authentication(): void
    {
        $this->getJson('/api/certifiers')->assertUnauthorized();
        $this->getJson('/api/certifiers/1')->assertUnauthorized();
        $this->getJson('/api/certifiers/1/applications')->assertUnauthorized();
        $this->getJson('/api/applications/1/certifiers')->assertUnauthorized();
    }

    public function test_certifiers_reject_invalid_enrichment_status(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->getJson('/api/certifiers?enrichment_status=not_a_status')
            ->assertStatus(422)
            ->assertJsonValidationErrors('enrichment_status');
    }

    public function test_certifiers_accept_enrichment_filters_validation(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        foreach (['pending', 'enriched', 'not_found', 'failed'] as $status) {
            $response = $this->getJson('/api/certifiers?enrichment_status='.$status);
            $this->assertNotSame(422, $response->status());
            $this->assertNotSame(401, $response->status());
        }

        foreach (['1', '0', 'true', 'false'] as $enriched) {
            $response = $this->getJson('/api/certifiers?enriched='.$enriched);
            $this->assertNotSame(422, $response->status());
            $this->assertNotSame(401, $response->status());
        }
    }

    public function test_application_contact_contribute_requires_role(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->postJson('/api/applications/1/contacts', ['name' => 'Acme Architects'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_application_contact_contribute_rejects_invalid_role(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->postJson('/api/applications/1/contacts', [
            'name' => 'Acme Architects',
            'role' => 'not_a_role',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_favourites_require_authentication(): void
    {
        $this->getJson('/api/user/favourites')->assertUnauthorized();
        $this->postJson('/api/user/favourites', ['application_id' => 1])->assertUnauthorized();
    }

    public function test_favourite_store_requires_application_id(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->postJson('/api/user/favourites', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('application_id');
    }

    public function test_claims_require_authentication(): void
    {
        $this->getJson('/api/user/claims')->assertUnauthorized();
        $this->getJson('/api/user/contact-profile')->assertUnauthorized();
        $this->postJson('/api/applications/1/claim', ['role' => 'architect'])->assertUnauthorized();
        $this->deleteJson('/api/applications/1/claim')->assertUnauthorized();
    }

    public function test_application_claim_requires_role(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->postJson('/api/applications/1/claim', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_application_claim_rejects_invalid_role(): void
    {
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->postJson('/api/applications/1/claim', ['role' => 'not_a_role'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_portfolio_mutations_require_authentication(): void
    {
        $this->postJson('/api/contacts/1/portfolio', [
            'application_id' => 1,
            'role' => 'architect',
        ])->assertUnauthorized();

        $this->deleteJson('/api/contacts/1/portfolio/1')->assertUnauthorized();
    }

    public function test_portfolio_store_validation_rules(): void
    {
        $rules = (new \App\Http\Requests\Warehouse\StoreContactPortfolioRequest)->rules();

        $validator = validator([], $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('application_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());

        $validator = validator([
            'application_id' => 1,
            'role' => 'not_a_role',
        ], $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
    }
}
