<?php

namespace Tests\Unit;

use App\Support\SqlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SqlGuardTest extends TestCase
{
    public function test_it_appends_a_limit_when_missing(): void
    {
        $this->assertSame(
            'select state from authorities WHERE amalgamated IS NULL limit 200',
            SqlGuard::sanitize('select state from authorities'),
        );
    }

    public function test_it_keeps_an_existing_limit(): void
    {
        $this->assertSame(
            'select state from authorities WHERE amalgamated IS NULL limit 5',
            SqlGuard::sanitize('select state from authorities limit 5'),
        );
    }

    public function test_it_strips_a_trailing_semicolon(): void
    {
        $this->assertSame(
            'select 1 limit 200',
            SqlGuard::sanitize('select 1;'),
        );
    }

    public function test_it_allows_cte_queries(): void
    {
        $sql = 'with t as (select 1 as n) select n from t limit 10';
        $this->assertSame($sql, SqlGuard::sanitize($sql));
    }

    #[DataProvider('unsafeQueries')]
    public function test_it_rejects_unsafe_queries(string $sql): void
    {
        $this->expectException(InvalidArgumentException::class);
        SqlGuard::sanitize($sql);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeQueries(): array
    {
        return [
            'insert' => ['insert into authorities (name) values (\'x\')'],
            'update' => ['update authorities set name = \'x\''],
            'delete' => ['delete from authorities'],
            'drop' => ['drop table authorities'],
            'alter' => ['alter table authorities add column x int'],
            'truncate' => ['truncate authorities'],
            'multi statement' => ['select 1; drop table authorities'],
            'not a select' => ['explain analyze select 1'],
            'system catalog' => ['select * from pg_catalog.pg_tables'],
            'information schema' => ['select * from information_schema.tables'],
            'empty' => ['   '],
        ];
    }

    public function test_it_rewrites_common_invented_contact_columns(): void
    {
        $this->assertSame(
            "SELECT name, phone FROM authorities WHERE name ILIKE '%Dungog%' AND amalgamated IS NULL LIMIT 10",
            SqlGuard::sanitize("SELECT name, postal_phone FROM authorities WHERE name ILIKE '%Dungog%' LIMIT 10"),
        );
    }

    public function test_it_rewrites_st_y_on_geography_geom(): void
    {
        $this->assertSame(
            'SELECT name FROM authorities WHERE state = \'NSW\' AND authorities.geom IS NOT NULL AND amalgamated IS NULL ORDER BY ST_YMax(authorities.geom::geometry) DESC LIMIT 10',
            SqlGuard::sanitize('SELECT name FROM authorities WHERE state = \'NSW\' ORDER BY ST_Y(geom) DESC LIMIT 10'),
        );
    }

    public function test_it_rewrites_st_x_on_cast_geometry_multipolygon(): void
    {
        $this->assertSame(
            'SELECT name, ST_XMax(authorities.geom::geometry) AS easting FROM authorities WHERE state = \'NSW\' AND authorities.geom IS NOT NULL AND amalgamated IS NULL ORDER BY ST_XMax(authorities.geom::geometry) DESC LIMIT 10',
            SqlGuard::sanitize('SELECT name, ST_X(geom::geometry) AS easting FROM authorities WHERE state = \'NSW\' AND geom IS NOT NULL ORDER BY ST_X(geom::geometry) DESC LIMIT 10'),
        );
    }

    public function test_it_excludes_amalgamated_authorities_by_default(): void
    {
        $this->assertSame(
            "SELECT name, region, state FROM authorities WHERE state = 'NSW' AND amalgamated IS NULL ORDER BY name LIMIT 10",
            SqlGuard::sanitize("SELECT name, region, state FROM authorities WHERE state = 'NSW' ORDER BY name LIMIT 10"),
        );
    }

    public function test_it_does_not_force_current_when_amalgamation_query(): void
    {
        $sql = "SELECT former.name FROM authorities former JOIN authorities successor ON successor.id = former.amalgamated WHERE former.amalgamated IS NOT NULL LIMIT 10";
        $this->assertSame($sql, SqlGuard::sanitize($sql));
    }

    public function test_it_excludes_null_suburbs_when_grouping_by_suburb(): void
    {
        $input = "SELECT l.suburb FROM locations l JOIN application_locations al ON al.location_id = l.id JOIN applications a ON a.id = al.application_id WHERE a.authority_id IN (SELECT id FROM authorities WHERE state = 'NSW' AND amalgamated IS NULL) GROUP BY l.suburb ORDER BY COUNT(*) DESC LIMIT 1";
        $out = SqlGuard::sanitize($input);
        $this->assertStringContainsString('l.suburb IS NOT NULL', $out);
        $this->assertStringContainsString("btrim(l.suburb) <> ''", $out);
    }

    public function test_it_rejects_missing_join_aliases(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown table alias: al');
        // Alias used without a repairable locations join pattern.
        SqlGuard::sanitize("SELECT COUNT(*) FROM applications a WHERE al.application_id = a.id LIMIT 10");
    }

    public function test_it_repairs_missing_al_join_and_approved_literal(): void
    {
        $out = SqlGuard::sanitize(
            "SELECT COUNT(*) AS count FROM applications a JOIN authorities auth ON auth.id = a.authority_id JOIN locations l ON l.id = al.location_id WHERE auth.name ILIKE '%Randwick%' AND a.decision = 'Approved' AND auth.amalgamated IS NULL LIMIT 200"
        );

        $this->assertStringContainsString('application_locations al ON al.application_id = a.id', $out);
        $this->assertStringContainsString('Operational consent issued', $out);
        $this->assertStringContainsString('Approval Conditional', $out);
        $this->assertStringNotContainsString("a.decision = 'Approved'", $out);
    }

    public function test_it_expands_nsw_approval_or_block_for_act(): void
    {
        $out = SqlGuard::sanitize(
            "SELECT COUNT(DISTINCT a.id) AS count FROM applications a JOIN authorities auth ON auth.id = a.authority_id JOIN application_decision_types adt ON adt.application_id = a.id JOIN decision_types dt ON dt.id = adt.decision_type_id LEFT JOIN decision_classes dc ON dc.id = dt.decision_class_id WHERE auth.state = 'ACT' AND auth.amalgamated IS NULL AND (dc.name ILIKE 'Approved' OR dt.name ILIKE '%Operational consent issued%' OR dt.name ILIKE '%Deferred Commencement%') LIMIT 200"
        );

        $this->assertStringContainsString('Approval Conditional', $out);
        $this->assertStringContainsString("dt.name ILIKE 'Approved'", $out);
    }

    public function test_it_rewrites_rejected_and_strips_deferred_commencement(): void
    {
        $out = SqlGuard::sanitize(
            "SELECT COUNT(DISTINCT a.id) AS count FROM applications a JOIN authorities auth ON auth.id = a.authority_id JOIN application_decision_types adt ON adt.application_id = a.id JOIN decision_types dt ON dt.id = adt.decision_type_id LEFT JOIN decision_classes dc ON dc.id = dt.decision_class_id WHERE auth.state = 'ACT' AND auth.amalgamated IS NULL AND (dc.name ILIKE 'Rejected' OR dt.name ILIKE '%Deferred Commencement%' OR dt.name ILIKE '%Operational consent refused%') LIMIT 200"
        );

        $this->assertStringContainsString("dt.name ILIKE 'Refused'", $out);
        $this->assertStringNotContainsString('Deferred Commencement', $out);
        $this->assertStringNotContainsString("dc.name ILIKE 'Rejected'", $out);
    }

    public function test_it_rejects_joining_application_types_on_a_type_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('applications.type is a portal string');
        SqlGuard::sanitize(
            "SELECT COUNT(DISTINCT a.id) AS count FROM applications a JOIN application_types apt ON apt.id = a.type WHERE apt.name ILIKE 'Class 2' LIMIT 200"
        );
    }

    public function test_it_repairs_missing_locations_alias_l(): void
    {
        $out = SqlGuard::sanitize(
            "SELECT COUNT(DISTINCT a.id) AS count FROM applications a JOIN authorities auth ON auth.id = a.authority_id WHERE auth.state = 'NSW' AND auth.amalgamated IS NULL AND l.suburb ILIKE '%St Ives%' LIMIT 200"
        );

        $this->assertStringContainsString('application_locations al ON al.application_id = a.id', $out);
        $this->assertStringContainsString('locations l ON l.id = al.location_id', $out);
    }

    public function test_it_qualifies_ambiguous_geom_when_locations_joined(): void
    {
        $out = SqlGuard::sanitize(
            "SELECT a.id, a.description, l.suburb, ROUND((ST_Area(geom)/1000000)::numeric, 0) AS area_km2 FROM applications a JOIN application_locations al ON al.application_id = a.id JOIN locations l ON l.id = al.location_id LEFT JOIN authorities auth ON auth.id = a.authority_id WHERE auth.state = 'NSW' AND auth.amalgamated IS NULL ORDER BY a.estimated_cost DESC LIMIT 5"
        );

        $this->assertStringContainsString('ST_Area(auth.geom)', $out);
        $this->assertStringContainsString('auth.geom IS NOT NULL', $out);
        $this->assertStringNotContainsString('ST_Area(geom)', $out);
    }

    public function test_it_rewrites_apartment_building_to_residential_flat(): void
    {
        $out = SqlGuard::sanitize(
            "SELECT a.id FROM applications a JOIN application_development_types adv ON adv.application_id = a.id JOIN development_types dvt ON dvt.id = adv.development_type_id WHERE dvt.name ILIKE '%Apartment building%' LIMIT 5"
        );

        $this->assertStringContainsString("%Residential flat%", $out);
        $this->assertStringNotContainsString('Apartment building', $out);
    }

    public function test_it_rewrites_this_month_from_current_date(): void
    {
        $out = SqlGuard::sanitize(
            'SELECT COUNT(*) AS count FROM applications WHERE submitted >= CURRENT_DATE LIMIT 200',
            question: 'How many applications were submitted this month?',
        );

        $this->assertStringContainsString("date_trunc('month', CURRENT_DATE)::date", $out);
        $this->assertStringContainsString("+ interval '1 month'", $out);
        $this->assertStringNotContainsString('submitted >= CURRENT_DATE', $out);
    }

    public function test_it_repairs_last_month_parens_and_bounds(): void
    {
        $out = SqlGuard::sanitize(
            "SELECT COUNT(*) AS count FROM applications WHERE submitted >= date_trunc('month', CURRENT_DATE) - interval '1 month')::date AND submitted < date_trunc('month', CURRENT_DATE) ::date LIMIT 200",
            question: 'How many last month?',
        );

        $this->assertSame(
            "SELECT COUNT(*) AS count FROM applications WHERE submitted >= (date_trunc('month', CURRENT_DATE) - interval '1 month')::date AND submitted < date_trunc('month', CURRENT_DATE)::date LIMIT 200",
            $out,
        );
    }
}
