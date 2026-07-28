<?php

namespace Tests\Feature;

use App\Ai\Agents\InsightsAgent;
use App\Models\User;
use App\Support\InsightsWarehouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InsightsAskTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.data' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        Schema::connection('data')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('email')->unique();
            $table->string('mobile')->nullable();
            $table->string('company')->nullable();
            $table->string('password');
            $table->boolean('is_verified')->default(true);
            $table->timestamps();
        });

        Schema::connection('data')->create('chat_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->timestamps();
        });

        Schema::connection('data')->create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id');
            $table->string('role');
            $table->text('content');
            $table->text('sql')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_returns_a_faked_agent_answer(): void
    {
        $this->mock(InsightsWarehouse::class, function ($mock): void {
            $mock->shouldReceive('hasData')->once()->andReturn(true);
        });

        InsightsAgent::fake([
            [
                'answer' => 'There are 128 NSW planning authorities in the warehouse.',
                'explanation' => 'Used get_stats with state NSW.',
                'confidence' => 'high',
            ],
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/insights/ask', [
            'question' => 'How many authorities are in NSW?',
        ]);

        $response->assertOk()
            ->assertJsonPath('answer', 'There are 128 NSW planning authorities in the warehouse.')
            ->assertJsonPath('confidence', 'high')
            ->assertJsonPath('thread_id', fn ($id) => is_int($id));

        InsightsAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'NSW'));
    }

    public function test_it_composes_a_fallback_when_the_model_returns_empty_json(): void
    {
        $this->mock(InsightsWarehouse::class, function ($mock): void {
            $mock->shouldReceive('hasData')->once()->andReturn(true);
        });

        InsightsAgent::fake([
            [
                'answer' => '[]',
                'explanation' => 'Listed authorities.',
                'confidence' => 'medium',
            ],
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/insights/ask', [
            'question' => 'List authorities in NSW',
        ]);

        $response->assertOk();

        $this->assertStringContainsString(
            'could not turn the tool results',
            strtolower((string) $response->json('answer')),
        );
    }

    public function test_it_reports_when_the_warehouse_has_no_data(): void
    {
        $this->mock(InsightsWarehouse::class, function ($mock): void {
            $mock->shouldReceive('hasData')->once()->andReturn(false);
        });

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/insights/ask', ['question' => 'How many councils?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The insights warehouse has no data yet. Load the shared data tables before asking questions.');
    }
}
