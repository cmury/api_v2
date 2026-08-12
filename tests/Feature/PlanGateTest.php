<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanGateTest extends TestCase
{
    public function test_core_can_request_planning_at_point_but_not_stats(): void
    {
        $this->actingAsPlan('core');

        $this->getJson('/api/planning-controls/at-point')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);

        $this->getJson('/api/stats?metric=decision_duration')
            ->assertForbidden()
            ->assertJsonPath('feature', 'analytics')
            ->assertJsonPath('required_plan', 'pro');
    }

    public function test_pro_can_request_stats_and_planning(): void
    {
        $this->actingAsPlan('pro');

        $this->getJson('/api/planning-controls/at-point')
            ->assertStatus(422);

        $response = $this->getJson('/api/stats?metric=decision_duration');
        $this->assertNotSame(401, $response->status());
        $this->assertNotSame(403, $response->status());
        $this->assertNotSame(422, $response->status());
    }

    public function test_free_authenticated_user_is_forbidden(): void
    {
        Sanctum::actingAs(new User(['email' => 'free@example.com']));

        $this->getJson('/api/planning-controls/at-point?lat=-33.8&lng=151.2')
            ->assertForbidden()
            ->assertJsonPath('required_plan', 'core');

        $this->getJson('/api/stats?metric=applications')
            ->assertForbidden()
            ->assertJsonPath('required_plan', 'pro');
    }
}
