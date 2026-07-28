<?php

namespace App\Support\Insights;

/**
 * Lightweight hints parsed from the user's question for routing and validation.
 */
final class QuestionIntent
{
    private const STATES = ['NSW', 'VIC', 'QLD', 'SA', 'WA', 'TAS', 'NT', 'ACT'];

    public function __construct(
        public readonly ?string $state,
        public readonly ?int $listLimit,
        public readonly ?string $authoritySearch,
        public readonly bool $wantsContact,
        public readonly ?string $contactField,
        public readonly bool $wantsLargestArea,
    ) {}

    public static function fromQuestion(string $question): self
    {
        return new self(
            state: self::extractState($question),
            listLimit: self::extractListLimit($question),
            authoritySearch: self::extractAuthoritySearch($question),
            wantsContact: self::extractWantsContact($question),
            contactField: self::extractContactField($question),
            wantsLargestArea: self::extractWantsLargestArea($question),
        );
    }

    public function mismatchedState(?string $resultState): bool
    {
        if ($this->state === null || $resultState === null || $resultState === '') {
            return false;
        }

        return strtoupper($resultState) !== $this->state;
    }

    public function matchesAuthorityName(string $name): bool
    {
        if ($this->authoritySearch === null || $this->authoritySearch === '') {
            return true;
        }

        $needle = strtolower($this->authoritySearch);
        $haystack = strtolower($name);

        return str_contains($haystack, $needle)
            || str_contains($needle, str_replace([' council', ' shire'], '', $haystack));
    }

    private static function extractState(string $question): ?string
    {
        foreach (self::STATES as $code) {
            if (preg_match('/\b'.preg_quote($code, '/').'\b/i', $question)) {
                return strtoupper($code);
            }
        }

        return null;
    }

    private static function extractListLimit(string $question): ?int
    {
        if (! preg_match('/\b(?:top\s+)?(\d{1,2})\b/i', $question, $matches)) {
            return null;
        }

        return max(1, min(50, (int) $matches[1]));
    }

    private static function extractWantsContact(string $question): bool
    {
        return (bool) preg_match(
            '/\b(phone|telephone|mobile|email|e-?mail|website|web\s*site|homepage|url|contact)\b/i',
            $question,
        );
    }

    private static function extractContactField(string $question): ?string
    {
        if (preg_match('/\b(phone|telephone|mobile)\b/i', $question)) {
            return 'phone';
        }
        if (preg_match('/\b(e-?mail)\b/i', $question)) {
            return 'email';
        }
        if (preg_match('/\b(website|web\s*site|homepage|url)\b/i', $question)) {
            return 'url';
        }

        return null;
    }

    private static function extractWantsLargestArea(string $question): bool
    {
        return (bool) preg_match(
            '/\b(?:greatest|largest|biggest)\s+area\b/i',
            $question,
        ) || (bool) preg_match(
            '/\bcover(?:s|ing)?\s+the\s+(?:greatest|largest|biggest)\b/i',
            $question,
        );
    }

    private static function extractAuthoritySearch(string $question): ?string
    {
        if (preg_match(
            '/\b(?:for|of)\s+(?:the\s+)?([A-Za-z][A-Za-z\'\-\s]{1,40}?)(?:\s+(?:Regional|Shire|City|Rural)?\s*Council)?\s*\??$/iu',
            $question,
            $matches,
        )) {
            return self::cleanAuthoritySearch($matches[1]);
        }

        if (preg_match(
            '/\b([A-Za-z][A-Za-z\'\-\s]{1,40}?)\s+(?:Regional|Shire|City|Rural)?\s*Council\b/iu',
            $question,
            $matches,
        )) {
            return self::cleanAuthoritySearch($matches[1]);
        }

        return null;
    }

    private static function cleanAuthoritySearch(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = preg_replace('/^(the|a)\s+/i', '', $value) ?? $value;

        if ($value === '' || preg_match('/^(phone|email|website|number)$/i', $value)) {
            return null;
        }

        return $value;
    }
}
