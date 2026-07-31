<?php

namespace App\Support\Insights;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * Builds a plain-English Insights reply from tool JSON when the LLM answer is empty/junk.
 * Small local models often call tools correctly then fail the final structured answer.
 */
final class AnswerComposer
{
    /**
     * @param  Collection<int, ToolResult>|iterable<int, ToolResult>  $toolResults
     * @return array{
     *     answer: string,
     *     rows: list<array<string, mixed>>,
     *     confidence: string,
     *     composed_from_tools: bool,
     *     warnings: list<string>
     * }
     */
    public static function compose(string $question, iterable $toolResults, ?string $llmAnswer): array
    {
        $llmAnswer = trim((string) $llmAnswer);
        $parsed = self::parse($toolResults, $question);
        $warnings = $parsed['warnings'] ?? [];

        if ($parsed !== null) {
            if (! self::isUsefulAnswer($llmAnswer) || self::looksLikeRawJson($llmAnswer)) {
                return [
                    'answer' => $parsed['answer'],
                    'rows' => $parsed['rows'],
                    'confidence' => self::confidenceFor($parsed, $warnings),
                    'composed_from_tools' => true,
                    'warnings' => $warnings,
                ];
            }
        }

        if (self::isUsefulAnswer($llmAnswer)) {
            return [
                'answer' => $llmAnswer,
                'rows' => $parsed['rows'] ?? [],
                'confidence' => 'medium',
                'composed_from_tools' => false,
                'warnings' => $warnings,
            ];
        }

        return [
            'answer' => $parsed['answer']
                ?? 'I could not turn the tool results into an answer. Try rephrasing the question.',
            'rows' => $parsed['rows'] ?? [],
            'confidence' => $parsed !== null ? self::confidenceFor($parsed, $warnings) : 'low',
            'composed_from_tools' => $parsed !== null,
            'warnings' => $warnings,
        ];
    }

    /**
     * Rows suitable for the Insights UI table (from the most relevant tool).
     *
     * @param  Collection<int, ToolResult>|iterable<int, ToolResult>  $toolResults
     * @return list<array<string, mixed>>
     */
    public static function rows(iterable $toolResults, string $question = ''): array
    {
        return self::parse($toolResults, $question)['rows'] ?? [];
    }

    public static function isUsefulAnswer(string $answer): bool
    {
        $answer = trim($answer);
        if ($answer === '' || strlen($answer) < 3) {
            return false;
        }

        $normalized = strtolower($answer);
        if (in_array($normalized, ['[]', '{}', 'null', 'none', 'n/a', '""', "''"], true)) {
            return false;
        }

        return ! self::looksLikeRawJson($answer);
    }

    public static function looksLikeRawJson(string $answer): bool
    {
        $trim = trim($answer);
        if ($trim === '' || ! in_array($trim[0], ['[', '{'], true)) {
            return false;
        }

        json_decode($trim);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * @param  Collection<int, ToolResult>|iterable<int, ToolResult>  $toolResults
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}|null
     */
    private static function parse(iterable $toolResults, string $question): ?array
    {
        $intent = QuestionIntent::fromQuestion($question);
        $decoded = [];

        foreach ($toolResults as $result) {
            if (! $result instanceof ToolResult) {
                continue;
            }

            $payload = json_decode((string) $result->result, true);
            if (! is_array($payload) || isset($payload['error'])) {
                continue;
            }

            $decoded[] = [
                'name' => $result->name,
                'payload' => $payload,
            ];
        }

        if ($decoded === []) {
            return null;
        }

        $byName = [];
        foreach ($decoded as $item) {
            $byName[$item['name']] = $item['payload'];
        }

        $warnings = [];

        if (isset($byName['search_authorities']['authorities']) && is_array($byName['search_authorities']['authorities'])) {
            return self::formatAuthorities($byName['search_authorities']['authorities'], QuestionIntent::fromQuestion($question), $warnings);
        }

        if (isset($byName['get_authority']) && is_array($byName['get_authority'])) {
            return self::formatAuthority($byName['get_authority'], QuestionIntent::fromQuestion($question), $warnings);
        }

        if (isset($byName['search_applications']['applications']) && is_array($byName['search_applications']['applications'])) {
            return self::formatApplications($byName['search_applications']['applications']);
        }

        if (isset($byName['search_applications_near_facility']['applications'])
            && is_array($byName['search_applications_near_facility']['applications'])
        ) {
            return self::formatApplicationsNearFacility($byName['search_applications_near_facility']);
        }

        if (isset($byName['get_application']) && is_array($byName['get_application'])) {
            return self::formatApplication($byName['get_application']);
        }

        if (isset($byName['search_locations']['locations']) && is_array($byName['search_locations']['locations'])) {
            return self::formatLocations($byName['search_locations']['locations'], $intent, $warnings);
        }

        if (isset($byName['search_facilities']['facilities']) && is_array($byName['search_facilities']['facilities'])) {
            return self::formatFacilities($byName['search_facilities']['facilities']);
        }

        if (isset($byName['get_stats']) && is_array($byName['get_stats'])) {
            return self::formatStats($byName['get_stats']);
        }

        if (isset($byName['get_forecast']) && is_array($byName['get_forecast'])) {
            return self::formatForecast($byName['get_forecast']);
        }

        if (isset($byName['list_taxonomies']['items']) && is_array($byName['list_taxonomies']['items'])) {
            return self::formatTaxonomies($byName['list_taxonomies']);
        }

        if (isset($byName['run_warehouse_sql']['rows']) && is_array($byName['run_warehouse_sql']['rows'])) {
            return self::formatSqlRows($byName['run_warehouse_sql']['rows']);
        }

        return null;
    }

    /**
     * @param  list<string>  $warnings
     */
    private static function confidenceFor(array $parsed, array $warnings): string
    {
        if ($warnings !== []) {
            return 'medium';
        }

        if (($parsed['rows'] ?? []) === []) {
            return 'low';
        }

        return 'medium';
    }

    /**
     * @param  list<array<string, mixed>>  $authorities
     * @param  list<string>  $warnings
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatAuthorities(array $authorities, QuestionIntent $intent, array &$warnings): array
    {
        if ($intent->state !== null) {
            $filtered = array_values(array_filter(
                $authorities,
                static fn (array $row) => ! $intent->mismatchedState(isset($row['state']) ? (string) $row['state'] : null),
            ));

            if ($filtered === [] && $authorities !== []) {
                $warnings[] = 'Results did not match the requested state ('.$intent->state.').';
            } elseif (count($filtered) < count($authorities)) {
                $warnings[] = 'Some results were outside '.$intent->state.' and were excluded.';
            }

            $authorities = $filtered;
        }

        if ($intent->authoritySearch !== null) {
            $filtered = array_values(array_filter(
                $authorities,
                static fn (array $row) => $intent->matchesAuthorityName((string) ($row['name'] ?? '')),
            ));

            if ($filtered === [] && $authorities !== []) {
                $warnings[] = 'Results did not match the council name "'.$intent->authoritySearch.'" from the question.';
            } elseif (count($filtered) < count($authorities)) {
                $warnings[] = 'Filtered out councils that did not match "'.$intent->authoritySearch.'".';
            }

            $authorities = $filtered;
        }

        if ($intent->wantsContact && $intent->authoritySearch !== null && count($authorities) === 1) {
            return self::formatAuthority($authorities[0], $intent, $warnings);
        }

        $limit = $intent->listLimit ?? 25;
        $rows = [];
        $lines = [];
        foreach (array_slice($authorities, 0, $limit) as $i => $row) {
            $name = (string) ($row['name'] ?? 'Unknown');
            $region = (string) ($row['region'] ?? '—');
            $state = (string) ($row['state'] ?? '');
            $lines[] = ($i + 1).'. '.$name.($region !== '' ? ' — '.$region : '').($state !== '' ? " ({$state})" : '');
            $rows[] = array_filter([
                'name' => $name,
                'region' => $row['region'] ?? null,
                'state' => $row['state'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'url' => $row['url'] ?? null,
                'applications_count' => $row['applications_count'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        if ($rows === []) {
            return [
                'answer' => $intent->state !== null
                    ? 'No authorities matched '.$intent->state.'. Try a narrower search or check the state filter.'
                    : 'No authorities matched that search.',
                'rows' => [],
                'warnings' => $warnings,
            ];
        }

        $more = count($authorities) > $limit ? "\n…and ".(count($authorities) - $limit).' more.' : '';
        $prefix = $intent->state !== null ? $intent->state.' authorities' : 'authorities';

        return [
            'answer' => 'Here are the '.$prefix." I found:\n".implode("\n", $lines).$more,
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $authority
     * @param  list<string>  $warnings
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatAuthority(array $authority, QuestionIntent $intent, array &$warnings): array
    {
        if ($intent->mismatchedState(isset($authority['state']) ? (string) $authority['state'] : null)) {
            $warnings[] = 'The authority state does not match '.$intent->state.' from the question.';
        }

        $name = (string) ($authority['name'] ?? 'That council');
        $bits = [];
        foreach (['phone', 'email', 'url', 'region', 'state', 'postal_address', 'postal_suburb', 'postal_code'] as $key) {
            if (! empty($authority[$key])) {
                $bits[] = str_replace('_', ' ', $key).': '.$authority[$key];
            }
        }

        return [
            'answer' => $bits === [] ? $name : $name.' — '.implode('; ', $bits).'.',
            'rows' => [$authority],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $applications
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatApplications(array $applications): array
    {
        $rows = [];
        $lines = [];
        foreach (array_slice($applications, 0, 15) as $i => $row) {
            $label = (string) ($row['authority_no'] ?? $row['description'] ?? 'Application');
            $extra = array_filter([
                $row['authority'] ?? null,
                isset($row['estimated_cost']) ? '$'.number_format((float) $row['estimated_cost']) : null,
                $row['submitted'] ?? null,
            ]);
            $lines[] = ($i + 1).'. '.$label.($extra !== [] ? ' — '.implode(', ', $extra) : '');
            $rows[] = $row;
        }

        return [
            'answer' => $lines === []
                ? 'No applications matched that search.'
                : "Here are the applications I found:\n".implode("\n", $lines),
            'rows' => $rows,
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatApplicationsNearFacility(array $payload): array
    {
        $applications = is_array($payload['applications'] ?? null) ? $payload['applications'] : [];
        $facility = is_array($payload['facility'] ?? null) ? $payload['facility'] : [];
        $radius = $payload['radius_meters'] ?? null;
        $facilityName = (string) ($facility['name'] ?? 'that facility');
        $radiusLabel = is_numeric($radius) ? number_format((float) $radius).'m' : 'the requested radius';

        $formatted = self::formatApplications($applications);
        if ($applications === []) {
            return [
                'answer' => 'No applications found within '.$radiusLabel.' of '.$facilityName.'.',
                'rows' => [],
                'warnings' => [],
            ];
        }

        $prefix = 'Applications within '.$radiusLabel.' of '.$facilityName.":\n";

        return [
            'answer' => $prefix.preg_replace('/^Here are the applications I found:\n/', '', $formatted['answer']),
            'rows' => $formatted['rows'],
            'warnings' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $facilities
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatFacilities(array $facilities): array
    {
        if ($facilities === []) {
            return [
                'answer' => 'No facilities matched that search.',
                'rows' => [],
                'warnings' => [],
            ];
        }

        $lines = [];
        foreach (array_values($facilities) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $n = $i + 1;
            $name = (string) ($row['name'] ?? 'Facility');
            $type = $row['facility_type'] ?? null;
            $state = $row['state'] ?? null;
            $extra = trim(implode(' · ', array_filter([(string) $type, (string) $state])));
            $lines[] = $extra !== '' ? "{$n}. {$name} — {$extra}" : "{$n}. {$name}";
        }

        return [
            'answer' => "Here are the facilities I found:\n".implode("\n", $lines),
            'rows' => array_values(array_filter($facilities, 'is_array')),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $application
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatApplication(array $application): array
    {
        $label = (string) ($application['authority_no'] ?? 'Application '.$application['id']);
        $desc = (string) ($application['description'] ?? '');

        return [
            'answer' => trim($label.($desc !== '' ? ': '.$desc : '')),
            'rows' => [$application],
            'warnings' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $locations
     * @param  list<string>  $warnings
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatLocations(array $locations, QuestionIntent $intent, array &$warnings): array
    {
        if ($intent->state !== null) {
            $locations = array_values(array_filter(
                $locations,
                static fn (array $row) => ! $intent->mismatchedState(isset($row['state']) ? (string) $row['state'] : null),
            ));

            if ($locations === []) {
                $warnings[] = 'No locations matched the requested state ('.$intent->state.').';
            }
        }

        $lines = [];
        foreach (array_slice($locations, 0, 15) as $i => $row) {
            $lines[] = ($i + 1).'. '.($row['formatted_address'] ?? $row['suburb'] ?? 'Location');
        }

        return [
            'answer' => $lines === []
                ? 'No locations matched that search.'
                : "Here are the locations I found:\n".implode("\n", $lines),
            'rows' => $locations,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatStats(array $stats): array
    {
        $metric = (string) ($stats['metric'] ?? 'metric');
        $scope = (string) ($stats['scope'] ?? 'all');

        if (isset($stats['labels'], $stats['values']) && is_array($stats['labels']) && is_array($stats['values'])) {
            $lines = [];
            $rows = [];
            foreach ($stats['labels'] as $i => $label) {
                $point = $stats['values'][$i] ?? null;
                $lines[] = '- '.$label.': '.(is_numeric($point) ? number_format((float) $point) : 'n/a');
                $rows[] = ['label' => $label, 'value' => $point];
            }

            return [
                'answer' => $lines === []
                    ? 'Chart data was returned but contained no points.'
                    : ucfirst($metric).' chart ('.$scope."):\n".implode("\n", $lines),
                'rows' => $rows,
                'warnings' => [],
            ];
        }

        $value = $stats['value'] ?? null;

        if (is_numeric($value)) {
            return [
                'answer' => 'The '.$metric.' total for '.$scope.' is '.number_format((float) $value).'.',
                'rows' => [['metric' => $metric, 'scope' => $scope, 'value' => $value]],
                'warnings' => [],
            ];
        }

        if (is_array($value)) {
            $lines = [];
            $rows = [];
            foreach (array_slice($value, 0, 15, true) as $key => $count) {
                if (is_array($count)) {
                    $label = (string) ($count['name'] ?? $count['label'] ?? $key);
                    $n = $count['count'] ?? $count['value'] ?? $count['n'] ?? null;
                } else {
                    $label = (string) $key;
                    $n = $count;
                }
                $lines[] = '- '.$label.': '.(is_numeric($n) ? number_format((float) $n) : json_encode($n));
                $rows[] = ['label' => $label, 'value' => $n];
            }

            return [
                'answer' => 'Breakdown of '.$metric.' ('.$scope."):\n".implode("\n", $lines),
                'rows' => $rows,
                'warnings' => [],
            ];
        }

        return [
            'answer' => 'Stats for '.$metric.' ('.$scope.'): '.json_encode($value),
            'rows' => [['metric' => $metric, 'scope' => $scope, 'value' => $value]],
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $forecast
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatForecast(array $forecast): array
    {
        $labels = $forecast['labels'] ?? [];
        $values = $forecast['values'] ?? [];
        $lines = [];
        $rows = [];

        if (is_array($labels) && is_array($values)) {
            foreach ($labels as $i => $label) {
                $point = $values[$i] ?? null;
                $lines[] = '- '.$label.': '.(is_numeric($point) ? number_format((float) $point) : 'n/a');
                $rows[] = ['period' => $label, 'point' => $point];
            }
        }

        return [
            'answer' => $lines === []
                ? 'Forecast generated, but no series points were returned.'
                : "Application volume forecast:\n".implode("\n", $lines),
            'rows' => $rows,
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $taxonomy
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatTaxonomies(array $taxonomy): array
    {
        $items = $taxonomy['items'] ?? [];
        $kind = (string) ($taxonomy['kind'] ?? 'taxonomy');
        $lines = [];
        foreach (array_slice($items, 0, 20) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $lines[] = '- '.($item['display_name'] ?? $item['name'] ?? json_encode($item));
        }

        return [
            'answer' => 'Available '.$kind.":\n".implode("\n", $lines),
            'rows' => array_values(array_filter($items, 'is_array')),
            'warnings' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}
     */
    private static function formatSqlRows(array $rows): array
    {
        if ($rows === []) {
            return [
                'answer' => 'The query ran successfully but returned no rows.',
                'rows' => [],
                'warnings' => [],
            ];
        }

        $lines = [];
        foreach (array_slice($rows, 0, 12) as $i => $row) {
            $bits = [];
            foreach ($row as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $bits[] = str_replace('_', ' ', (string) $key).' '.$value;
            }
            $lines[] = ($i + 1).'. '.implode(', ', $bits);
        }

        return [
            'answer' => "Here is what I found:\n".implode("\n", $lines),
            'rows' => $rows,
            'warnings' => [],
        ];
    }
}
