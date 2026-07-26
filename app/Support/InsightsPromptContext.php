<?php

namespace App\Support;

/**
 * Builds a follow-up-aware prompt for InsightsAgent from recent chat messages.
 * Small local models often ignore Conversational history; embedding the last
 * successful SQL + sample rows in the user prompt is more reliable.
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
            if (($row['role'] ?? '') === 'assistant' && ! empty($row['sql'])) {
                $lastAssistant = $row;
            }
        }

        if ($lastAssistant === null) {
            return $question;
        }

        $payload = is_array($lastAssistant['payload'] ?? null) ? $lastAssistant['payload'] : [];
        $isError = (bool) ($payload['error'] ?? false);

        if ($isError) {
            return self::enrichFailedAttempt($question, $lastUser, $lastAssistant, $payload);
        }

        $sample = self::sampleRows($payload);

        $parts = [
            'This is a follow-up in an ongoing chat. Reuse filters/entities from the previous',
            'query unless the user clearly changes the topic. Short requests like "add the',
            'application number", "what type is this?", "show the address", or "those" refer',
            'to the previous result set — modify that SQL rather than inventing a new topic.',
            '',
            'Previous user question: '.trim((string) ($lastUser['content'] ?? '')),
            'Previous SQL: '.trim((string) $lastAssistant['sql']),
        ];

        if ($sample !== '') {
            $parts[] = 'Previous result sample (JSON): '.$sample;
        }

        $parts[] = '';
        $parts[] = 'Current user request: '.$question;

        return implode("\n", $parts);
    }

    /**
     * @param  array{role?: string, content?: string}|null  $lastUser
     * @param  array{sql?: ?string, content?: string}  $lastAssistant
     * @param  array<string, mixed>  $payload
     */
    private static function enrichFailedAttempt(string $question, ?array $lastUser, array $lastAssistant, array $payload): string
    {
        $reason = (string) ($payload['reason'] ?? $payload['error'] ?? $lastAssistant['content'] ?? 'unknown error');
        if (is_string($payload['error'] ?? null) && ($payload['reason'] ?? null) === null) {
            // payload['error'] may be bool true from fail(); prefer reason/message content
            $reason = (string) ($payload['reason'] ?? $lastAssistant['content'] ?? 'unknown error');
        }
        if (isset($payload['reason']) && is_string($payload['reason'])) {
            $reason = $payload['reason'];
        } elseif (isset($payload['error']) && is_string($payload['error'])) {
            $reason = $payload['error'];
        } else {
            $reason = trim((string) ($lastAssistant['content'] ?? 'unknown error'));
        }

        return implode("\n", [
            'The previous SQL FAILED and must be replaced with a corrected query.',
            'Do NOT repeat the same broken SQL. Fix the error below.',
            '',
            'Original user question: '.trim((string) ($lastUser['content'] ?? '')),
            'Failed SQL: '.trim((string) ($lastAssistant['sql'] ?? '')),
            'Error: '.$reason,
            '',
            'Hints:',
            '- Every alias used (e.g. al, l, auth) must appear in FROM/JOIN.',
            '- Council approval counts: join applications → authorities only (no locations).',
            '- Use decision taxonomy (application_decision_types → decision_types), not',
            "  a.decision = 'Approved'. Approvals differ by state:",
            "  NSW ≈ 'Operational consent issued' / 'Deferred Commencement';",
            "  ACT ≈ 'Approval Conditional' / 'Approved'.",
            "  Refused/rejected ≈ 'Refused' / 'Deemed refused' / '%consent refused%' — never Deferred Commencement.",
            '- Never JOIN application_types ON apt.id = a.type (a.type is a string).',
            "- BCA 'Class 2' → development_classes.development_class = '2' via",
            '  application_development_types → development_types (not application_types).',
            '- Suburb filters need application_locations + locations.',
            '- Relative dates: "this month" uses date_trunc(\'month\', CURRENT_DATE) bounds on',
            '  applications.submitted — never submitted >= CURRENT_DATE (that is today-or-future).',
            '- Example approval shape:',
            '  SELECT COUNT(DISTINCT a.id) AS count',
            '  FROM applications a',
            '  JOIN authorities auth ON auth.id = a.authority_id',
            '  JOIN application_decision_types adt ON adt.application_id = a.id',
            '  JOIN decision_types dt ON dt.id = adt.decision_type_id',
            '  LEFT JOIN decision_classes dc ON dc.id = dt.decision_class_id',
            "  WHERE auth.name ILIKE '%CouncilName%' AND auth.amalgamated IS NULL",
            "    AND (dc.name ILIKE 'Approved' OR dt.name ILIKE '%Operational consent issued%'",
            "         OR dt.name ILIKE '%Deferred Commencement%'",
            "         OR dt.name ILIKE 'Approval Conditional' OR dt.name ILIKE 'Approved')",
            '  LIMIT 200',
            '',
            'Current user request: '.$question,
        ]);
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
