<?php

namespace App\Support\Warehouse;

/**
 * Shared list sort parsing for warehouse index endpoints.
 */
final class ListOrdering
{
    /**
     * @return array{0: string, 1: string}
     */
    public static function parse(mixed $order, string $default = 'name'): array
    {
        $order = (string) ($order ?: $default);
        if (str_starts_with($order, '-')) {
            return [substr($order, 1), 'desc'];
        }

        return [$order, 'asc'];
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function column(string $column, array $allowed, string $default = 'name'): string
    {
        return in_array($column, $allowed, true) ? $column : $default;
    }
}
