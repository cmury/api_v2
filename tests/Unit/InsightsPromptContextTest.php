<?php

namespace Tests\Unit;

use App\Support\Insights\InsightsPromptContext;
use PHPUnit\Framework\TestCase;

class InsightsPromptContextTest extends TestCase
{
    public function test_it_returns_question_when_no_history(): void
    {
        $this->assertSame('How many apps?', InsightsPromptContext::enrich('How many apps?', []));
    }

    public function test_it_embeds_previous_tools_for_follow_ups(): void
    {
        $prompt = InsightsPromptContext::enrich('add the application number', [
            ['role' => 'user', 'content' => 'List applications in Muswellbrook'],
            [
                'role' => 'assistant',
                'content' => 'Listed applications',
                'payload' => [
                    'tools' => [
                        ['name' => 'search_applications', 'arguments' => ['suburb' => 'Muswellbrook']],
                    ],
                    'rows' => [['description' => 'Entertainment facility.']],
                ],
            ],
        ]);

        $this->assertStringContainsString('search_applications', $prompt);
        $this->assertStringContainsString('Muswellbrook', $prompt);
        $this->assertStringContainsString('Current user request: add the application number', $prompt);
        $this->assertStringContainsString('Entertainment facility', $prompt);
    }

    public function test_it_asks_to_replace_failed_tools_on_retry(): void
    {
        $prompt = InsightsPromptContext::enrich('try that again', [
            ['role' => 'user', 'content' => 'How many applications were approved in Randwick council?'],
            [
                'role' => 'assistant',
                'content' => 'Could not answer that question.',
                'payload' => [
                    'error' => true,
                    'tools' => [
                        ['name' => 'get_stats', 'arguments' => ['authority_id' => 42]],
                    ],
                    'reason' => 'No applications matched the filters.',
                ],
            ],
        ]);

        $this->assertStringContainsString('FAILED', $prompt);
        $this->assertStringContainsString('get_stats', $prompt);
        $this->assertStringContainsString('No applications matched the filters.', $prompt);
        $this->assertStringContainsString('Do NOT repeat', $prompt);
        $this->assertStringContainsString('Current user request: try that again', $prompt);
    }
}
