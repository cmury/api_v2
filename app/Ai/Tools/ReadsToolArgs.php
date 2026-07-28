<?php

namespace App\Ai\Tools;

use Laravel\Ai\Tools\Request;

/**
 * Laravel AI Tool Request has InteractsWithData + ArrayAccess, but not HTTP Request::input().
 */
trait ReadsToolArgs
{
    protected function arg(Request $request, string $key, mixed $default = null): mixed
    {
        return $request[$key] ?? $default;
    }

    protected function argString(Request $request, string $key, string $default = ''): string
    {
        $value = $this->arg($request, $key);

        return is_string($value) || is_numeric($value) ? trim((string) $value) : $default;
    }

    protected function argInt(Request $request, string $key, ?int $default = null): ?int
    {
        $value = $this->arg($request, $key);
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    protected function hasArg(Request $request, string $key): bool
    {
        $value = $this->arg($request, $key);

        return $value !== null && $value !== '';
    }
}
