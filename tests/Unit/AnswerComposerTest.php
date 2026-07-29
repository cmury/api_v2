<?php

namespace Tests\Unit;

use App\Support\Insights\AnswerComposer;
use App\Support\Insights\InsightsPromptContext;
use Laravel\Ai\Responses\Data\ToolResult;
use PHPUnit\Framework\TestCase;

class AnswerComposerTest extends TestCase
{
    public function test_it_rejects_empty_json_answers(): void
    {
        $this->assertFalse(AnswerComposer::isUsefulAnswer('[]'));
        $this->assertFalse(AnswerComposer::isUsefulAnswer('{}'));
        $this->assertTrue(AnswerComposer::looksLikeRawJson('[{"name":"x"}]'));
        $this->assertTrue(AnswerComposer::isUsefulAnswer('Here are 10 NSW authorities.'));
    }

    public function test_it_composes_authority_list_from_tool_results(): void
    {
        $tool = new ToolResult(
            id: '1',
            name: 'search_authorities',
            arguments: ['state' => 'NSW'],
            result: json_encode([
                'count' => 2,
                'authorities' => [
                    ['name' => 'Albury City Council', 'region' => 'Murray Region', 'state' => 'NSW'],
                    ['name' => 'Ballina Shire Council', 'region' => 'Richmond Tweed Region', 'state' => 'NSW'],
                ],
            ]),
        );

        $composed = AnswerComposer::compose('List 10 authorities in NSW', [$tool], '[]');

        $this->assertStringContainsString('Albury City Council', $composed['answer']);
        $this->assertStringContainsString('Murray Region', $composed['answer']);
        $this->assertStringContainsString('Ballina Shire Council', $composed['answer']);
        $this->assertTrue($composed['composed_from_tools']);
        $this->assertSame('medium', $composed['confidence']);

        $rows = AnswerComposer::rows([$tool], 'List 10 authorities in NSW');
        $this->assertCount(2, $rows);
        $this->assertSame('Albury City Council', $rows[0]['name']);
    }

    public function test_it_filters_authorities_outside_requested_state(): void
    {
        $tool = new ToolResult(
            id: '1',
            name: 'search_authorities',
            arguments: [],
            result: json_encode([
                'count' => 2,
                'authorities' => [
                    ['name' => 'Adelaide City Council', 'region' => 'Metro', 'state' => 'SA'],
                    ['name' => 'Albury City Council', 'region' => 'Murray Region', 'state' => 'NSW'],
                ],
            ]),
        );

        $composed = AnswerComposer::compose('List authorities in NSW', [$tool], '[]');

        $this->assertStringContainsString('Albury City Council', $composed['answer']);
        $this->assertStringNotContainsString('Adelaide', $composed['answer']);
        $this->assertNotEmpty($composed['warnings']);
    }

    public function test_it_filters_unrelated_councils_when_question_names_one(): void
    {
        $tool = new ToolResult(
            id: '1',
            name: 'search_authorities',
            arguments: [],
            result: json_encode([
                'count' => 3,
                'authorities' => [
                    ['name' => 'Adelaide City Council', 'state' => 'SA', 'phone' => '08 0000 0000'],
                    ['name' => 'Dungog Shire Council', 'state' => 'NSW', 'phone' => '02 4995 7777'],
                    ['name' => 'Albury City Council', 'state' => 'NSW', 'phone' => '02 6023 8111'],
                ],
            ]),
        );

        $composed = AnswerComposer::compose('What is phone number for Dungog Council', [$tool], '[]');

        $this->assertStringContainsString('02 4995 7777', $composed['answer']);
        $this->assertStringNotContainsString('Adelaide', $composed['answer']);
    }

    public function test_it_formats_chart_stats_with_labels_and_values(): void
    {
        $tool = new ToolResult(
            id: '1',
            name: 'get_stats',
            arguments: ['metric' => 'applications', 'mode' => 'chart'],
            result: json_encode([
                'metric' => 'applications',
                'scope' => 'all',
                'labels' => ['Jan', 'Feb'],
                'values' => [120, 98],
            ]),
        );

        $composed = AnswerComposer::compose('Show application chart', [$tool], '[]');

        $this->assertStringContainsString('Jan', $composed['answer']);
        $this->assertStringContainsString('120', $composed['answer']);
        $this->assertSame('Feb', $composed['rows'][1]['label']);
    }

    public function test_it_formats_applications_near_transit_stop(): void
    {
        $tool = new ToolResult(
            id: '1',
            name: 'search_applications_near_transit_stop',
            arguments: ['stop_search' => 'Chatswood', 'radius' => 1000],
            result: json_encode([
                'transit_stop' => ['id' => 9, 'name' => 'Chatswood Railway Station', 'stop_type' => 'train'],
                'radius_meters' => 1000,
                'count' => 1,
                'applications' => [
                    [
                        'authority_no' => 'DA/2024/1',
                        'authority' => 'Willoughby City Council',
                        'estimated_cost' => 2500000,
                        'submitted' => '2024-06-01',
                    ],
                ],
            ]),
        );

        $composed = AnswerComposer::compose(
            'Construction Certificates for apartment buildings within 1km of Chatswood Railway Station',
            [$tool],
            '[]',
        );

        $this->assertStringContainsString('Chatswood Railway Station', $composed['answer']);
        $this->assertStringContainsString('1,000m', $composed['answer']);
        $this->assertStringContainsString('DA/2024/1', $composed['answer']);
        $this->assertTrue($composed['composed_from_tools']);
    }

    public function test_it_formats_transit_stop_search(): void
    {
        $tool = new ToolResult(
            id: '1',
            name: 'search_transit_stops',
            arguments: ['search' => 'Central'],
            result: json_encode([
                'count' => 1,
                'transit_stops' => [
                    ['name' => 'Central Railway Station', 'stop_type' => 'train', 'state' => 'NSW'],
                ],
            ]),
        );

        $composed = AnswerComposer::compose('Find Central station', [$tool], '[]');

        $this->assertStringContainsString('Central Railway Station', $composed['answer']);
        $this->assertStringContainsString('train', $composed['answer']);
    }

    public function test_prompt_context_includes_previous_tool_arguments(): void
    {
        $prompt = InsightsPromptContext::enrich('add the region', [
            ['role' => 'user', 'content' => 'List councils in NSW'],
            [
                'role' => 'assistant',
                'content' => 'Listed councils',
                'payload' => [
                    'tools' => [
                        ['name' => 'search_authorities', 'arguments' => ['state' => 'NSW', 'per_page' => 10]],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('search_authorities', $prompt);
        $this->assertStringContainsString('NSW', $prompt);
        $this->assertStringNotContainsString('Previous SQL:', $prompt);
    }
}
