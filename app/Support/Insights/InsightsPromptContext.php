<?php

namespace App\Support\Insights;

/**
 * Builds a follow-up-aware prompt for InsightsAgent from recent chat messages.
 * Small local models often ignore Conversational history; embedding the last
 * turn in the user prompt is more reliable.
 */
class InsightsPromptContext
{
    /**
     * @param  list<array{role: string, content: string, sql?: ?string, payload?: mixed}>  $history
     */
    public static function enrich(string $question, array $history): string
    {
        $lastUser = null;
        $lastAssistant = null;

        foreach ($history as $row) {
            if (($row['role'] ?? '') === 'user') {
                $lastUser = $row;
            }
            if (($row['role'] ?? '') === 'assistant') {
                $lastAssistant = $row;
            }
        }

        if ($lastAssistant === null) {
            return $question;
        }

        if (self::isStandaloneQuestion($question)) {
            return $question;
        }

        $payload = is_array($lastAssistant['payload'] ?? null) ? $lastAssistant['payload'] : [];
        $isError = (bool) ($payload['error'] ?? false);

        if ($isError) {
            return self::enrichFailedAttempt($question, $lastUser, $lastAssistant, $payload);
        }

        $parts = [
            'This is a follow-up in an ongoing chat. Reuse entities/filters from the previous',
            'turn unless the user clearly changes topic. Short requests like "add the',
            'application number", "what type is this?", or "those" refer to the prior result.',
            '',
            'Previous user question: '.trim((string) ($lastUser['content'] ?? '')),
            'Previous assistant answer: '.trim((string) ($lastAssistant['content'] ?? '')),
        ];

        $toolSummary = self::formatToolSummary($payload);
        if ($toolSummary !== '') {
            $parts[] = $toolSummary;
        }

        if ($toolSummary === '' && is_string($lastAssistant['sql'] ?? null) && $lastAssistant['sql'] !== '') {
            $parts[] = 'Previous SQL: '.trim((string) $lastAssistant['sql']);
        }

        $sample = self::sampleRows($payload);
        if ($sample !== '') {
            $parts[] = 'Previous result sample (JSON): '.$sample;
        }

        $parts[] = '';
        $parts[] = 'Current user request: '.$question;

        return implode("\n", $parts);
    }

    /**
     * True when the question should start fresh (ignore prior turn context).
     */
    public static function isStandaloneQuestion(string $question): bool
    {
        $q = trim($question);
        if ($q === '') {
            return true;
        }

        if (preg_match('/^(what about|how about|try again|try that|same for)\b/i', $q)) {
            return false;
        }

        if (preg_match(
            '/\b(add (the )?(application )?number|those|that one|what type( of)?( (is|was) this)?|include the )\b/i',
            $q,
        )) {
            return false;
        }

        if (preg_match(
            '/\b(phone|e-?mail|web\s*site|website|homepage|url|contact|postal\s*address)\b/i',
            $q,
        )) {
            return true;
        }

        if (preg_match('/\b(give me|what(?:\'s| is)|find|show me)\b.+\bfor\b.+/i', $q)) {
            return true;
        }

        if (preg_match(
            '/\b(most |largest|smallest|northern|southern|eastern|western|how many|which authority|which council|forecast)\b/i',
            $q,
        )) {
            return true;
        }

        return mb_strlen($q) >= 48;
    }

    /**
     * @param  array{role?: string, content?: string}|null  $lastUser
     * @param  array{sql?: ?string, content?: string}  $lastAssistant
     * @param  array<string, mixed>  $payload
     */
    private static function enrichFailedAttempt(string $question, ?array $lastUser, array $lastAssistant, array $payload): string
    {
        if (isset($payload['reason']) && is_string($payload['reason']) && $payload['reason'] !== '') {
            $reason = $payload['reason'];
        } elseif (isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== '') {
            $reason = $payload['error'];
        } else {
            $reason = trim((string) ($lastAssistant['content'] ?? 'unknown error'));
        }

        $parts = [
            'The previous attempt FAILED and must be replaced with a corrected approach.',
            'Do NOT repeat the same broken tool call or SQL.',
            '',
            'Original user question: '.trim((string) ($lastUser['content'] ?? '')),
            'Error: '.$reason,
        ];

        $toolSummary = self::formatToolSummary($payload);
        if ($toolSummary !== '') {
            $parts[] = $toolSummary;
        } elseif (is_string($lastAssistant['sql'] ?? null) && $lastAssistant['sql'] !== '') {
            $parts[] = 'Failed SQL: '.trim((string) $lastAssistant['sql']);
        }

        $parts[] = '';
        $parts[] = 'Hints:';
        $parts[] = '- Prefer warehouse tools (search_authorities, search_applications, get_stats, list_taxonomies,';
        $parts[] = '  search_facilities, search_applications_near_facility, get_planning_at_point,';
        $parts[] = '  search_planning_controls)';
        $parts[] = '  with the correct filters (especially state / source / layer when the user names one).';
        $parts[] = '- Use run_warehouse_sql only when REST tools cannot express the question.';
        $parts[] = '';
        $parts[] = 'Current user request: '.$question;

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function formatToolSummary(array $payload): string
    {
        if (empty($payload['tools']) || ! is_array($payload['tools'])) {
            return '';
        }

        $summaries = [];
        foreach ($payload['tools'] as $tool) {
            if (! is_array($tool)) {
                continue;
            }

            $name = (string) ($tool['name'] ?? 'tool');
            $args = $tool['arguments'] ?? null;
            if (is_array($args) && $args !== []) {
                $encoded = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $summaries[] = $name.($encoded ? ' '.$encoded : '');
            } else {
                $summaries[] = $name;
            }
        }

        return $summaries === [] ? '' : 'Previous tools: '.implode('; ', $summaries);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function sampleRows(array $payload): string
    {
        $rows = $payload['rows'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return '';
        }

        $slice = array_slice($rows, 0, 5);
        $encoded = json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }
}
