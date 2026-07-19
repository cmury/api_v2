<?php

namespace App\Support;

/**
 * Product / warehouse Postgres connection (imby_data_v2).
 * Schema is owned by agents_v2 migrations — this API reads/writes via models.
 */
final class DataDatabase
{
    public static function name(): string
    {
        return (string) config('database.data_connection', 'data');
    }
}
