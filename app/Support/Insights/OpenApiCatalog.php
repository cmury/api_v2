<?php

namespace App\Support\Insights;

/**
 * Lightweight OpenAPI helper for Insights grounding.
 * Prefers the committed docs/openapi.json snapshot (same source as Scramble export).
 */
final class OpenApiCatalog
{
    private const SPEC_PATH = 'docs/openapi.json';

    /**
     * High-level "when to call which endpoint" guidance from the OpenAPI info block.
     */
    public static function intentGuide(): string
    {
        $spec = self::load();
        $description = (string) ($spec['info']['description'] ?? '');

        if ($description === '') {
            return self::fallbackGuide();
        }

        return trim($description);
    }

    /**
     * Return matching warehouse paths for a free-text query (keyword overlap).
     *
     * @return list<array{path: string, method: string, operation_id: ?string, summary: string, parameters: list<string>}>
     */
    public static function search(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $tokens = self::tokens($query);
        $hits = [];

        foreach (self::load()['paths'] ?? [] as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (! is_array($operation) || ! is_string($method)) {
                    continue;
                }

                $method = strtoupper($method);
                if (! in_array($method, ['GET', 'POST'], true)) {
                    continue;
                }

                // Insights is a warehouse Q&A surface — skip auth/user/docs noise.
                if (preg_match('#^/(auth|user|insights|status)(/|$)#', (string) $path)) {
                    continue;
                }

                $haystack = strtolower(implode(' ', [
                    $path,
                    $operation['operationId'] ?? '',
                    $operation['summary'] ?? '',
                    $operation['description'] ?? '',
                    implode(' ', $operation['tags'] ?? []),
                ]));

                $score = 0;
                foreach ($tokens as $token) {
                    if (str_contains($haystack, $token)) {
                        $score++;
                    }
                }

                if ($score === 0) {
                    continue;
                }

                $params = [];
                foreach ($operation['parameters'] ?? [] as $parameter) {
                    if (is_array($parameter) && isset($parameter['name'])) {
                        $params[] = (string) $parameter['name'];
                    }
                }

                $hits[] = [
                    'score' => $score,
                    'path' => (string) $path,
                    'method' => $method,
                    'operation_id' => isset($operation['operationId']) ? (string) $operation['operationId'] : null,
                    'summary' => trim((string) ($operation['summary'] ?? $operation['description'] ?? $operation['operationId'] ?? $path)),
                    'parameters' => array_values(array_unique($params)),
                ];
            }
        }

        usort($hits, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            static fn (array $hit): array => [
                'path' => $hit['path'],
                'method' => $hit['method'],
                'operation_id' => $hit['operation_id'],
                'summary' => $hit['summary'] !== '' ? $hit['summary'] : $hit['path'],
                'parameters' => $hit['parameters'],
            ],
            array_slice($hits, 0, $limit),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        $path = base_path(self::SPEC_PATH);
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $query): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', strtolower($query)) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $t): bool => strlen($t) >= 3 && ! in_array($t, ['the', 'and', 'for', 'how', 'many', 'what', 'which', 'with'], true),
        ));
    }

    private static function fallbackGuide(): string
    {
        return <<<'TXT'
IMBY warehouse API intents:
- Counts / aggregates → GetStats (GET /stats)
- Charts → GetStats with chart mode (GET /charts)
- Browse applications / authorities / locations → Search* tools
- Near a facility → SearchApplicationsNearFacility (GET /facilities/applications-near)
- Facility lookup → SearchFacilities (GET /facilities)
- Zoning / controls at a point → GetPlanningAtPoint (GET /planning-controls/at-point)
- Browse planning layers → SearchPlanningControls (GET /planning-controls)
- Contacts / professionals → SearchContacts (GET /contacts)
- Building certifiers (Fair Trading register) → SearchCertifiers (GET /certifiers)
- Entity detail → GetAuthority / GetApplication
- Filter vocabulary → ListTaxonomies
- Novel joins the REST surface cannot express → RunWarehouseSql (last resort)
TXT;
    }
}
