<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Validates and normalises AI-generated SQL before it is executed.
 *
 * This is defense-in-depth on top of the read-only `data_readonly` database
 * role: even if a query slips past this guard it can never mutate data, but the
 * guard keeps the surface small (single read-only statement, row-capped, no
 * access to Postgres system catalogs).
 */
class SqlGuard
{
    public const MAX_ROWS = 200;

    /**
     * Write / DDL keywords that must never appear in a generated query.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'insert', 'update', 'delete', 'drop', 'alter', 'create', 'truncate',
        'grant', 'revoke', 'copy', 'merge', 'vacuum', 'reindex',
    ];

    /**
     * Return a sanitised, row-capped read-only query or throw if it is unsafe.
     *
     * @throws InvalidArgumentException
     */
    public static function sanitize(string $sql, int $maxRows = self::MAX_ROWS): string
    {
        $clean = trim($sql);

        // Strip a single trailing semicolon.
        $clean = trim((string) preg_replace('/;\s*$/', '', $clean));

        if ($clean === '') {
            throw new InvalidArgumentException('Empty query.');
        }

        // Single statement only.
        if (str_contains($clean, ';')) {
            throw new InvalidArgumentException('Only a single statement is allowed.');
        }

        // Must be a read-only query.
        if (! preg_match('/^\s*(select|with)\b/i', $clean)) {
            throw new InvalidArgumentException('Only SELECT queries are allowed.');
        }

        // Reject write / DDL keywords.
        foreach (self::FORBIDDEN as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $clean)) {
                throw new InvalidArgumentException("Disallowed keyword: {$keyword}.");
            }
        }

        // Block access to Postgres system catalogs.
        if (preg_match('/\b(pg_[a-z_]+|information_schema)\b/i', $clean)) {
            throw new InvalidArgumentException('Access to system catalogs is not allowed.');
        }

        // Enforce a row cap.
        if (! preg_match('/\blimit\b/i', $clean)) {
            $clean .= ' limit '.$maxRows;
        }

        return $clean;
    }
}
