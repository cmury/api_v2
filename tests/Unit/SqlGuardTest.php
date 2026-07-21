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
            'select state from authorities limit 200',
            SqlGuard::sanitize('select state from authorities'),
        );
    }

    public function test_it_keeps_an_existing_limit(): void
    {
        $this->assertSame(
            'select state from authorities limit 5',
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
}
