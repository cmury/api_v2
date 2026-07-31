<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Resolves product Insights chat table names.
 *
 * Fresh agents_v2 installs use users_chat_*; older DBs may still have chat_*.
 */
final class ProductChatTables
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function threads(): string
    {
        return self::resolve('users_chat_threads', 'chat_threads');
    }

    public static function messages(): string
    {
        return self::resolve('users_chat_messages', 'chat_messages');
    }

    /**
     * Clear cached resolution (tests).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function resolve(string $preferred, string $legacy): string
    {
        if (isset(self::$cache[$preferred])) {
            return self::$cache[$preferred];
        }

        $connection = DataDatabase::name();

        if (Schema::connection($connection)->hasTable($preferred)) {
            return self::$cache[$preferred] = $preferred;
        }

        if (Schema::connection($connection)->hasTable($legacy)) {
            return self::$cache[$preferred] = $legacy;
        }

        return self::$cache[$preferred] = $preferred;
    }
}
