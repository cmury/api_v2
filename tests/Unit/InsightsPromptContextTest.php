<?php

namespace Tests\Unit;

use App\Support\InsightsPromptContext;
use PHPUnit\Framework\TestCase;

class InsightsPromptContextTest extends TestCase
{
    public function test_it_returns_question_when_no_history(): void
    {
        $this->assertSame('How many apps?', InsightsPromptContext::enrich('How many apps?', []));
    }

    public function test_it_embeds_previous_sql_for_follow_ups(): void
    {
        $prompt = InsightsPromptContext::enrich('add the application number', [
            ['role' => 'user', 'content' => 'List applications in Muswellbrook'],
            [
                'role' => 'assistant',
                'content' => 'Listed applications',
                'sql' => "SELECT a.description FROM applications a JOIN locations l ON true WHERE l.suburb ILIKE '%MUSWELLBROOK%'",
                'payload' => ['rows' => [['description' => 'Entertainment facility.']]],
            ],
        ]);

        $this->assertStringContainsString('Previous SQL:', $prompt);
        $this->assertStringContainsString('MUSWELLBROOK', $prompt);
        $this->assertStringContainsString('Current user request: add the application number', $prompt);
        $this->assertStringContainsString('Entertainment facility', $prompt);
    }

    public function test_it_asks_to_replace_failed_sql_on_retry(): void
    {
        $prompt = InsightsPromptContext::enrich('try that again', [
            ['role' => 'user', 'content' => 'How many applications were approved in Randwick council?'],
            [
                'role' => 'assistant',
                'content' => 'Could not build a safe query for that question.',
                'sql' => "SELECT COUNT(*) FROM applications a JOIN locations l ON l.id = al.location_id",
                'payload' => ['error' => true, 'reason' => 'Unknown table alias: al.'],
            ],
        ]);

        $this->assertStringContainsString('FAILED', $prompt);
        $this->assertStringContainsString('Unknown table alias: al.', $prompt);
        $this->assertStringContainsString('Do NOT repeat', $prompt);
        $this->assertStringContainsString('decision taxonomy', $prompt);
        $this->assertStringContainsString('Current user request: try that again', $prompt);
    }
}
