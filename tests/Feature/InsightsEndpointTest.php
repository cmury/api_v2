<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InsightsEndpointTest extends TestCase
{
    public function test_it_requires_authentication(): void
    {
        $this->postJson('/api/insights/ask', ['question' => 'How many authorities?'])
            ->assertUnauthorized();
    }

    public function test_it_validates_the_question(): void
    {
        // Authenticate without touching the database; validation runs before the
        // agent/DB are ever invoked, so this exercises the endpoint contract only.
        Sanctum::actingAs(new User(['email' => 'tester@example.com']));

        $this->postJson('/api/insights/ask', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_it_defaults_insights_provider_from_config(): void
    {
        $this->assertSame('openai', config('imby.insights_provider'));
        $this->assertSame('gpt-4.1-mini', config('imby.insights_model'));
    }
}
