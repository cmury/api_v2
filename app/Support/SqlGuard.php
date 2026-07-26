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
     * Common LLM column hallucinations → real warehouse columns.
     * Applied as whole-identifier replacements (word boundaries).
     *
     * @var array<string, string>
     */
    private const COLUMN_ALIASES = [
        'postal_phone' => 'phone',
        'council_phone' => 'phone',
        'contact_phone' => 'phone',
        'telephone' => 'phone',
        'phone_number' => 'phone',
        'postal_email' => 'email',
        'contact_email' => 'email',
        'council_email' => 'email',
        'website' => 'url',
        'website_url' => 'url',
        'postal_url' => 'url',
        'address' => 'postal_address',
        'post_address' => 'postal_address',
        'postcode' => 'postal_code',
        'zip' => 'postal_code',
        'zip_code' => 'postal_code',
    ];

    /**
     * Common PostGIS mistakes against geography(MultiPolygon) columns.
     *
     * @var array<string, string>
     */
    private const GEOGRAPHY_REWRITES = [
        // Bare geography — cast + use envelope extremes (polygons are not points).
        '/\bST_Y\s*\(\s*geom\s*\)/i' => 'ST_YMax(geom::geometry)',
        '/\bST_X\s*\(\s*geom\s*\)/i' => 'ST_XMax(geom::geometry)',
        // Already cast to geometry but still invalid on MultiPolygon.
        '/\bST_Y\s*\(\s*geom\s*::\s*geometry\s*\)/i' => 'ST_YMax(geom::geometry)',
        '/\bST_X\s*\(\s*geom\s*::\s*geometry\s*\)/i' => 'ST_XMax(geom::geometry)',
        // Envelope helpers without cast.
        '/\bST_YMax\s*\(\s*geom\s*\)/i' => 'ST_YMax(geom::geometry)',
        '/\bST_YMin\s*\(\s*geom\s*\)/i' => 'ST_YMin(geom::geometry)',
        '/\bST_XMax\s*\(\s*geom\s*\)/i' => 'ST_XMax(geom::geometry)',
        '/\bST_XMin\s*\(\s*geom\s*\)/i' => 'ST_XMin(geom::geometry)',
        '/\bST_Centroid\s*\(\s*geom\s*\)/i' => 'ST_Centroid(geom::geometry)',
    ];

    /**
     * Return a sanitised, row-capped read-only query or throw if it is unsafe.
     *
     * @throws InvalidArgumentException
     */
    public static function sanitize(string $sql, int $maxRows = self::MAX_ROWS, ?string $question = null): string
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

        $clean = self::rewriteColumnAliases($clean);
        $clean = self::rewriteGeographyFunctions($clean);
        $clean = self::repairMissingApplicationLocationsJoin($clean);
        $clean = self::repairMissingLocationsAlias($clean);
        $clean = self::rewriteApprovedDecisionLiteral($clean);
        $clean = self::expandApprovalDecisionOrBlock($clean);
        $clean = self::rewriteRejectedDecisionFilters($clean);
        $clean = self::rewriteApartmentBuildingType($clean);
        $clean = self::repairDateTruncCastParens($clean);
        $clean = self::applyRelativeDateIntent($clean, $question);
        $clean = self::ensureGeomNotNull($clean);
        $clean = self::qualifyBareGeom($clean);
        $clean = self::ensureCurrentAuthorities($clean);
        $clean = self::ensureSuburbNotNullWhenGrouped($clean);
        self::assertNoFakeApplicationTypeFk($clean);
        self::assertKnownAliases($clean);

        // Enforce a row cap.
        if (! preg_match('/\blimit\b/i', $clean)) {
            $clean .= ' limit '.$maxRows;
        }

        return $clean;
    }

    /**
     * Rewrite known invented column names to real ones (small local models guess often).
     */
    private static function rewriteColumnAliases(string $sql): string
    {
        foreach (self::COLUMN_ALIASES as $wrong => $right) {
            $sql = (string) preg_replace('/\b'.preg_quote($wrong, '/').'\b/i', $right, $sql);
        }

        return $sql;
    }

    /**
     * Cast geography geom to geometry for ST_X/ST_Y family functions.
     */
    private static function rewriteGeographyFunctions(string $sql): string
    {
        foreach (self::GEOGRAPHY_REWRITES as $pattern => $replacement) {
            $sql = (string) preg_replace($pattern, $replacement, $sql);
        }

        return $sql;
    }

    /**
     * Insert the missing application_locations pivot when alias `al` is used without a JOIN.
     */
    private static function repairMissingApplicationLocationsJoin(string $sql): string
    {
        if (! preg_match('/\bal\./i', $sql)) {
            return $sql;
        }

        if (preg_match('/\bapplication_locations\s+al\b/i', $sql)) {
            return $sql;
        }

        return (string) preg_replace(
            '/\bJOIN\s+locations\s+(\w+)\s+ON\s+\1\.id\s*=\s*al\.location_id\b/i',
            'JOIN application_locations al ON al.application_id = a.id JOIN locations $1 ON $1.id = al.location_id',
            $sql,
            1,
        );
    }

    /**
     * When alias `l` is used without a locations JOIN, attach the standard suburb joins.
     */
    private static function repairMissingLocationsAlias(string $sql): string
    {
        if (! preg_match('/\bl\./i', $sql)) {
            return $sql;
        }

        if (preg_match('/\blocations\s+l\b/i', $sql)) {
            return $sql;
        }

        $joins = 'JOIN application_locations al ON al.application_id = a.id JOIN locations l ON l.id = al.location_id';

        if (preg_match('/\bapplication_locations\s+al\b/i', $sql)) {
            return (string) preg_replace(
                '/\b(where|group\s+by|order\s+by|having|limit)\b/i',
                'JOIN locations l ON l.id = al.location_id $1',
                $sql,
                1,
            );
        }

        if (preg_match('/\bfrom\s+applications\s+(?:as\s+)?a\b/i', $sql)) {
            return (string) preg_replace(
                '/\bfrom\s+applications\s+(?:as\s+)?a\b/i',
                'FROM applications a '.$joins,
                $sql,
                1,
            );
        }

        return $sql;
    }

    /**
     * NSW/ACT portal statuses are not always literally 'Approved' — expand the common mistake.
     */
    private static function rewriteApprovedDecisionLiteral(string $sql): string
    {
        return (string) preg_replace(
            '/\b(?:a\.)?decision\s*=\s*\'Approved\'/i',
            '(a.decision ILIKE \'%Operational consent issued%\' OR a.decision ILIKE \'%Deferred Commencement%\' OR a.decision ILIKE \'Approval Conditional\' OR a.decision ILIKE \'Approved%\')',
            $sql,
        );
    }

    /**
     * Expand NSW-centric approved OR-blocks so ACT "Approval Conditional" also matches.
     */
    private static function expandApprovalDecisionOrBlock(string $sql): string
    {
        if (stripos($sql, 'Approval Conditional') !== false) {
            return $sql;
        }

        if (! preg_match('/Operational consent issued/i', $sql)) {
            return $sql;
        }

        // Insert ACT approval strings before the closing paren of the approved OR-group when present.
        return (string) preg_replace(
            '/(dt\.name\s+ILIKE\s+\'%Deferred Commencement%\'(?:\s+OR\s+dt\.name\s+ILIKE\s+\'Approved%?\')?)/i',
            '$1 OR dt.name ILIKE \'Approval Conditional\' OR dt.name ILIKE \'Approved\'',
            $sql,
            1,
        );
    }

    /**
     * Map "Rejected" filters to refused statuses; strip Deferred Commencement from refuse filters.
     */
    private static function rewriteRejectedDecisionFilters(string $sql): string
    {
        $isRefuseIntent = (bool) preg_match(
            '/\b(Rejected|Refused|consent refused|Deemed refused)\b/i',
            $sql,
        );

        if (! $isRefuseIntent) {
            return $sql;
        }

        $sql = (string) preg_replace(
            '/dc\.name\s+ILIKE\s+\'Rejected\'/i',
            '(dc.name ILIKE \'Refused\' OR dt.name ILIKE \'Refused\' OR dt.name ILIKE \'Deemed refused\' OR dt.name ILIKE \'%consent refused%\')',
            $sql,
        );

        // Deferred Commencement is an approval pathway, never a refusal.
        $sql = (string) preg_replace(
            '/\s*OR\s+dt\.name\s+ILIKE\s+\'%Deferred Commencement%\'/i',
            '',
            $sql,
        );

        return $sql;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertNoFakeApplicationTypeFk(string $sql): void
    {
        if (preg_match('/application_types\s+\w+\s+ON\s+\w+\.id\s*=\s*(?:a\.)?type\b/i', $sql)) {
            throw new InvalidArgumentException(
                'Invalid join: applications.type is a portal string, not application_types.id. Use application_application_types (or application_development_types for Class/BCA).',
            );
        }

        if (preg_match('/\b\w+\.development_type_id\b/i', $sql)
            && preg_match('/\bapplication_types\b/i', $sql)
            && ! preg_match('/\bapplication_development_types\b/i', $sql)
        ) {
            throw new InvalidArgumentException(
                'Invalid join: application_types has no development_type_id. Use application_development_types → development_types.',
            );
        }
    }

    /**
     * Spatial ranking on geom must exclude NULL boundaries (Postgres DESC puts NULLs first).
     */
    private static function ensureGeomNotNull(string $sql): string
    {
        if (! preg_match('/\bST_(YMax|YMin|XMax|XMin|Centroid|Area)\s*\(\s*(?:\w+\.)?geom/i', $sql)) {
            return $sql;
        }

        if (preg_match('/\b(?:\w+\.)?geom\s+IS\s+NOT\s+NULL\b/i', $sql)) {
            return $sql;
        }

        $clause = 'geom IS NOT NULL';
        $alias = self::authoritiesAlias($sql);
        if ($alias !== null) {
            $clause = $alias.'.geom IS NOT NULL';
        }

        if (preg_match('/\bwhere\b/i', $sql)) {
            return (string) preg_replace(
                '/\b(order\s+by|group\s+by|having|limit)\b/i',
                'AND '.$clause.' $1',
                $sql,
                1,
            );
        }

        return (string) preg_replace(
            '/\b(order\s+by|group\s+by|having|limit)\b/i',
            'WHERE '.$clause.' $1',
            $sql,
            1,
        );
    }

    /**
     * Qualify bare `geom` as authorities.geom when both authorities and locations are joined
     * (both tables have a geom column — Postgres raises ambiguous column otherwise).
     */
    private static function qualifyBareGeom(string $sql): string
    {
        $authAlias = self::authoritiesAlias($sql);
        if ($authAlias === null) {
            return $sql;
        }

        $hasLocations = (bool) preg_match('/\blocations\b/i', $sql);
        $hasBareGeom = (bool) preg_match('/(?<![.\w])geom\b/i', $sql);
        if (! $hasBareGeom) {
            return $sql;
        }

        // Always qualify when locations are present; also when ST_* uses bare geom with auth.
        if (! $hasLocations && ! preg_match('/\bST_\w+\s*\(\s*geom\b/i', $sql)) {
            return $sql;
        }

        return (string) preg_replace('/(?<![.\w])geom\b/i', $authAlias.'.geom', $sql);
    }

    /**
     * Fix missing opening paren before date_trunc − interval casts.
     * e.g. date_trunc(...) - interval '1 month')::date → (date_trunc(...) - interval '1 month')::date
     */
    private static function repairDateTruncCastParens(string $sql): string
    {
        return (string) preg_replace(
            '/(?<!\()\bdate_trunc\s*\(\s*\'(month|week|year)\'\s*,\s*CURRENT_DATE\s*\)\s*-\s*interval\s+\'1\s+\1\'\s*\)\s*::\s*date/i',
            '(date_trunc(\'$1\', CURRENT_DATE) - interval \'1 $1\')::date',
            $sql,
        );
    }

    /**
     * When the user asks for a relative period, force correct calendar bounds on submitted/decision_date.
     */
    private static function applyRelativeDateIntent(string $sql, ?string $question): string
    {
        if ($question === null || trim($question) === '') {
            return $sql;
        }

        $col = self::dateColumnForIntent($sql, $question);
        $predicate = null;

        if (preg_match('/\b(this|current)\s+month\b/i', $question)) {
            $predicate = "{$col} >= date_trunc('month', CURRENT_DATE)::date"
                ." AND {$col} < (date_trunc('month', CURRENT_DATE) + interval '1 month')::date";
        } elseif (preg_match('/\b(last|previous)\s+month\b/i', $question)) {
            $predicate = "{$col} >= (date_trunc('month', CURRENT_DATE) - interval '1 month')::date"
                ." AND {$col} < date_trunc('month', CURRENT_DATE)::date";
        } elseif (preg_match('/\b(this|current)\s+week\b/i', $question)) {
            $predicate = "{$col} >= date_trunc('week', CURRENT_DATE)::date"
                ." AND {$col} < (date_trunc('week', CURRENT_DATE) + interval '1 week')::date";
        } elseif (preg_match('/\b(this|current)\s+year\b/i', $question)) {
            $predicate = "{$col} >= date_trunc('year', CURRENT_DATE)::date"
                ." AND {$col} < (date_trunc('year', CURRENT_DATE) + interval '1 year')::date";
        } elseif (preg_match('/\btoday\b/i', $question)) {
            $predicate = "{$col} = CURRENT_DATE";
        } elseif (preg_match('/\byesterday\b/i', $question)) {
            $predicate = "{$col} = (CURRENT_DATE - 1)";
        } elseif (preg_match('/\b(last|past)\s+30\s+days\b/i', $question)) {
            $predicate = "{$col} >= (CURRENT_DATE - INTERVAL '30 days') AND {$col} <= CURRENT_DATE";
        }

        if ($predicate === null) {
            return $sql;
        }

        return self::replaceDateColumnPredicate($sql, $col, $predicate);
    }

    private static function dateColumnForIntent(string $sql, string $question): string
    {
        if (preg_match('/\bdecision_date\b/i', $sql) && preg_match('/\bdecision/i', $question)) {
            return 'decision_date';
        }

        if (preg_match('/\b(?:a\.)?decision_date\b/i', $sql) && ! preg_match('/\b(?:a\.)?submitted\b/i', $sql)) {
            return 'decision_date';
        }

        return 'submitted';
    }

    /**
     * Replace an existing date-column filter, or inject one before LIMIT/ORDER/GROUP.
     */
    private static function replaceDateColumnPredicate(string $sql, string $col, string $predicate): string
    {
        $c = preg_quote($col, '/');

        if (preg_match(
            '/\bWHERE\b(.+?)(?=\s+(?:GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT)\b|$)/is',
            $sql,
            $m,
        )) {
            $whereBody = trim($m[1]);
            $mentionsCol = (bool) preg_match('/\b(?:a\.)?'.$c.'\b/i', $whereBody);
            $hasOtherFilters = (bool) preg_match(
                '/\b(?:auth|authorities|suburb|state|dvt|dvc|dt|dc|apt|adv|adt)\b/i',
                $whereBody,
            );

            if ($mentionsCol && ! $hasOtherFilters) {
                $sql = (string) preg_replace(
                    '/\bWHERE\b.+?(?=\s+(?:GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT)\b|$)/is',
                    'WHERE '.$predicate.' ',
                    $sql,
                    1,
                );
            } elseif ($mentionsCol) {
                $stripped = (string) preg_replace(
                    '/(?:\s*(?:AND|OR)\s*)?(?:a\.)?'.$c.'\s*(?:=|>=|>|<=|<)\s*(?:\([^)]*\)|date_trunc\([^)]*\)|CURRENT_DATE)(?:\s*[+-]\s*(?:interval\s+\'[^\']+\'|\d+))?(?:\s*::\s*\w+)?/i',
                    '',
                    $whereBody,
                );
                $stripped = trim((string) preg_replace('/\s+/', ' ', $stripped));
                $stripped = (string) preg_replace('/^(?:AND|OR)\s+/i', '', $stripped);
                $stripped = (string) preg_replace('/\s+(?:AND|OR)$/i', '', $stripped);

                $newWhere = $stripped === '' ? $predicate : $predicate.' AND '.$stripped;
                $sql = (string) preg_replace(
                    '/\bWHERE\b.+?(?=\s+(?:GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT)\b|$)/is',
                    'WHERE '.$newWhere.' ',
                    $sql,
                    1,
                );
            } else {
                $sql = (string) preg_replace('/\bWHERE\b/i', 'WHERE '.$predicate.' AND', $sql, 1);
            }
        } else {
            $sql = (string) preg_replace(
                '/\b(GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT)\b/i',
                'WHERE '.$predicate.' $1',
                $sql,
                1,
            );
        }

        return (string) preg_replace('/\s+/', ' ', trim($sql));
    }

    /**
     * NSW portal uses "Residential flat building", not "Apartment building".
     */
    private static function rewriteApartmentBuildingType(string $sql): string
    {
        return (string) preg_replace(
            '/ILIKE\s+\'%Apartment building%\'/i',
            "ILIKE '%Residential flat%'",
            $sql,
        );
    }

    /**
     * @return non-empty-string|null
     */
    private static function authoritiesAlias(string $sql): ?string
    {
        $reserved = [
            'where', 'join', 'left', 'inner', 'right', 'full', 'cross', 'on',
            'order', 'group', 'limit', 'having', 'union', 'except', 'intersect',
            'as', 'and', 'or', 'set', 'select', 'from',
        ];

        if (preg_match('/\b(?:from|join)\s+authorities\s+(?:as\s+)?([a-z_][\w]*)/i', $sql, $m)
            && ! in_array(strtolower($m[1]), $reserved, true)
        ) {
            return $m[1];
        }

        if (preg_match('/\b(?:from|join)\s+authorities\b/i', $sql)) {
            return 'authorities';
        }

        return null;
    }

    /**
     * Default authority queries to current councils only (exclude former/amalgamated rows).
     * Skipped when the SQL already references `amalgamated` (explicit amalgamation questions).
     */
    private static function ensureCurrentAuthorities(string $sql): string
    {
        if (! preg_match('/\bauthorities\b/i', $sql)) {
            return $sql;
        }

        if (preg_match('/\bamalgamated\b/i', $sql)) {
            return $sql;
        }

        $reserved = [
            'where', 'join', 'left', 'inner', 'right', 'full', 'cross', 'on',
            'order', 'group', 'limit', 'having', 'union', 'except', 'intersect',
            'as', 'and', 'or', 'set', 'select', 'from',
        ];

        $clause = 'amalgamated IS NULL';
        if (preg_match('/\b(?:from|join)\s+authorities\s+(?:as\s+)?([a-z_][\w]*)/i', $sql, $m)
            && ! in_array(strtolower($m[1]), $reserved, true)
        ) {
            $clause = $m[1].'.amalgamated IS NULL';
        }

        if (preg_match('/\bwhere\b/i', $sql)) {
            return (string) preg_replace(
                '/\b(order\s+by|group\s+by|having|limit)\b/i',
                'AND '.$clause.' $1',
                $sql,
                1,
            );
        }

        if (preg_match('/\b(order\s+by|group\s+by|having|limit)\b/i', $sql)) {
            return (string) preg_replace(
                '/\b(order\s+by|group\s+by|having|limit)\b/i',
                'WHERE '.$clause.' $1',
                $sql,
                1,
            );
        }

        return rtrim($sql).' WHERE '.$clause;
    }

    /**
     * Suburb rankings must ignore NULL/blank suburbs (otherwise NULL wins COUNT(*) DESC).
     */
    private static function ensureSuburbNotNullWhenGrouped(string $sql): string
    {
        if (! preg_match('/\bgroup\s+by\b[^;]*\b(?:\w+\.)?suburb\b/i', $sql)) {
            return $sql;
        }

        if (preg_match('/\b(?:\w+\.)?suburb\s+IS\s+NOT\s+NULL\b/i', $sql)) {
            return $sql;
        }

        $clause = 'suburb IS NOT NULL AND btrim(suburb) <> \'\'';
        if (preg_match('/\bgroup\s+by\s+([a-z_][\w]*)\.suburb\b/i', $sql, $m)) {
            $alias = $m[1];
            $clause = $alias.'.suburb IS NOT NULL AND btrim('.$alias.'.suburb) <> \'\'';
        }

        if (preg_match('/\bwhere\b/i', $sql)) {
            return (string) preg_replace(
                '/\b(group\s+by|order\s+by|having|limit)\b/i',
                'AND '.$clause.' $1',
                $sql,
                1,
            );
        }

        return (string) preg_replace(
            '/\b(group\s+by|order\s+by|having|limit)\b/i',
            'WHERE '.$clause.' $1',
            $sql,
            1,
        );
    }

    /**
     * Reject queries that reference a table alias never introduced in FROM/JOIN.
     *
     * @throws InvalidArgumentException
     */
    private static function assertKnownAliases(string $sql): void
    {
        $declared = [];

        if (preg_match_all(
            '/\b(?:from|join)\s+([a-z_][\w]*)\s+(?:as\s+)?([a-z_][\w]*)\b/i',
            $sql,
            $matches,
            PREG_SET_ORDER,
        )) {
            $reserved = [
                'where', 'join', 'left', 'inner', 'right', 'full', 'cross', 'on',
                'order', 'group', 'limit', 'having', 'union', 'except', 'intersect',
                'as', 'and', 'or', 'select', 'from', 'using', 'natural',
            ];

            foreach ($matches as $match) {
                $table = strtolower($match[1]);
                $alias = strtolower($match[2]);
                $declared[$table] = true;
                if (! in_array($alias, $reserved, true)) {
                    $declared[$alias] = true;
                }
            }
        }

        // Unaliased table names: FROM applications / JOIN authorities
        if (preg_match_all('/\b(?:from|join)\s+([a-z_][\w]*)\b/i', $sql, $tables)) {
            foreach ($tables[1] as $table) {
                $declared[strtolower($table)] = true;
            }
        }

        if ($declared === []) {
            return;
        }

        if (! preg_match_all('/\b([a-z_][\w]*)\s*\./i', $sql, $used)) {
            return;
        }

        foreach (array_unique($used[1]) as $alias) {
            $key = strtolower($alias);
            if (in_array($key, ['public', 'st', 'date', 'count', 'sum', 'avg', 'max', 'min'], true)) {
                continue;
            }
            if (! isset($declared[$key])) {
                throw new InvalidArgumentException("Unknown table alias: {$alias}.");
            }
        }
    }
}
