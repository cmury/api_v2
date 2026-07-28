<?php

namespace App\Support\Insights;

use App\Models\Authority;
use App\Support\Warehouse\AuthoritySearch;

/**
 * Answers common Insights questions deterministically when the LLM would pick wrong tool args.
 */
final class InsightsDirectResolver
{
    public function __construct(
        private readonly AuthoritySearch $authoritySearch = new AuthoritySearch,
    ) {}

    /**
     * @return array{
     *     answer: string,
     *     explanation: string,
     *     confidence: string,
     *     rows: list<array<string, mixed>>,
     *     tools: list<array{name: string, arguments: array<string, mixed>, result_preview: string, calls: int}>,
     *     composed_from_tools: bool,
     *     warnings: list<string>,
     *     sql: ?string,
     *     row_source: ?string
     * }|null
     */
    public function try(string $question): ?array
    {
        $intent = QuestionIntent::fromQuestion($question);

        if ($intent->wantsLargestArea) {
            return $this->largestArea($intent);
        }

        if ($intent->wantsContact && $intent->authoritySearch !== null) {
            return $this->authorityContact($intent);
        }

        return null;
    }

    /**
     * @return array{
     *     answer: string,
     *     explanation: string,
     *     confidence: string,
     *     rows: list<array<string, mixed>>,
     *     tools: list<array{name: string, arguments: array<string, mixed>, result_preview: string, calls: int}>,
     *     composed_from_tools: bool,
     *     warnings: list<string>,
     *     sql: ?string,
     *     row_source: ?string
     * }|null
     */
    private function largestArea(QuestionIntent $intent): ?array
    {
        $rows = $this->authoritySearch->rankedByArea($intent->state, 5);
        if ($rows->isEmpty()) {
            return null;
        }

        /** @var Authority $largest */
        $largest = $rows->first();
        $areaKm2 = number_format((float) ($largest->area_km2 ?? 0));
        $scope = $intent->state !== null ? $intent->state : 'Australia';

        $tableRows = $rows->map(fn (Authority $authority) => [
            'name' => $authority->name,
            'state' => $authority->state,
            'region' => $authority->region,
            'area_km2' => $authority->area_km2 ?? null,
            'applications_count' => $authority->applications_count,
        ])->all();

        $lines = [];
        foreach (array_slice($tableRows, 0, 5) as $i => $row) {
            $lines[] = ($i + 1).'. '.$row['name'].' — '.number_format((float) ($row['area_km2'] ?? 0)).' km²';
        }

        return [
            'answer' => $largest->name.' covers the largest area in '.$scope.' at '.$areaKm2." km².\n"
                ."Top councils by LGA area:\n".implode("\n", $lines),
            'explanation' => 'Ranked current councils by PostGIS boundary area'
                .($intent->state !== null ? ' in '.$intent->state : '').'.',
            'confidence' => 'high',
            'rows' => $tableRows,
            'tools' => [[
                'name' => 'search_authorities',
                'arguments' => array_filter([
                    'state' => $intent->state,
                    'order' => '-area_km2',
                    'per_page' => 5,
                ]),
                'result_preview' => '',
                'calls' => 1,
            ]],
            'composed_from_tools' => true,
            'warnings' => [],
            'sql' => null,
            'row_source' => 'tools',
        ];
    }

    /**
     * @return array{
     *     answer: string,
     *     explanation: string,
     *     confidence: string,
     *     rows: list<array<string, mixed>>,
     *     tools: list<array{name: string, arguments: array<string, mixed>, result_preview: string, calls: int}>,
     *     composed_from_tools: bool,
     *     warnings: list<string>,
     *     sql: ?string,
     *     row_source: ?string
     * }
     */
    private function authorityContact(QuestionIntent $intent): array
    {
        $authority = $this->authoritySearch->findBestMatch($intent->authoritySearch, $intent->state);
        $arguments = array_filter([
            'search' => $intent->authoritySearch,
            'state' => $intent->state,
            'per_page' => 5,
        ]);

        if (! $authority instanceof Authority) {
            return [
                'answer' => 'I could not find a council matching "'.$intent->authoritySearch.'"'
                    .($intent->state !== null ? ' in '.$intent->state : '').'.',
                'explanation' => 'Searched authorities by council name from the question.',
                'confidence' => 'low',
                'rows' => [],
                'tools' => [[
                    'name' => 'search_authorities',
                    'arguments' => $arguments,
                    'result_preview' => '',
                    'calls' => 1,
                ]],
                'composed_from_tools' => true,
                'warnings' => ['No authority matched the council name in the question.'],
                'sql' => null,
                'row_source' => 'tools',
            ];
        }

        $field = $intent->contactField;
        $value = match ($field) {
            'phone' => $authority->phone,
            'email' => $authority->email,
            'url' => $authority->url,
            default => $authority->phone ?: $authority->email ?: $authority->url,
        };

        $label = match ($field) {
            'phone' => 'phone number',
            'email' => 'email address',
            'url' => 'website',
            default => 'contact detail',
        };

        $row = [
            'name' => $authority->name,
            'state' => $authority->state,
            'region' => $authority->region,
            'phone' => $authority->phone,
            'email' => $authority->email,
            'url' => $authority->url,
            'applications_count' => $authority->applications_count,
        ];

        if ($value === null || $value === '') {
            return [
                'answer' => $authority->name.' is in the warehouse but has no '.$label.' on record.',
                'explanation' => 'Matched council name and checked authority contact fields.',
                'confidence' => 'medium',
                'rows' => [$row],
                'tools' => [[
                    'name' => 'search_authorities',
                    'arguments' => $arguments,
                    'result_preview' => '',
                    'calls' => 1,
                ]],
                'composed_from_tools' => true,
                'warnings' => [],
                'sql' => null,
                'row_source' => 'tools',
            ];
        }

        return [
            'answer' => 'The '.$label.' for '.$authority->name.' is '.$value.'.',
            'explanation' => 'Matched council name in the question and looked up authority contact fields.',
            'confidence' => 'high',
            'rows' => [$row],
            'tools' => [[
                'name' => 'search_authorities',
                'arguments' => $arguments,
                'result_preview' => '',
                'calls' => 1,
            ]],
            'composed_from_tools' => true,
            'warnings' => [],
            'sql' => null,
            'row_source' => 'tools',
        ];
    }
}
