<?php

namespace App\Support\Insights;

/**
 * Compact JSON encoding for AI tool results (row-capped, UTF-8 safe).
 */
final class ToolJson
{
    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     */
    public static function encode(array $payload, int $maxBytes = 12_000): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return '{"error":"Unable to encode tool result."}';
        }

        if (strlen($encoded) <= $maxBytes) {
            return $encoded;
        }

        return json_encode([
            'truncated' => true,
            'note' => 'Result truncated for the model; ask a narrower question or raise per_page carefully.',
            'preview' => mb_substr($encoded, 0, $maxBytes - 200),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"error":"truncated"}';
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function rows(array $rows, int $limit = 25): array
    {
        return array_map(
            static function (object|array $row): array {
                $data = is_array($row) ? $row : get_object_vars($row);

                return array_map(
                    static fn ($v) => is_string($v) && mb_strlen($v) > 240 ? mb_substr($v, 0, 237).'…' : $v,
                    $data,
                );
            },
            array_slice($rows, 0, $limit),
        );
    }
}
