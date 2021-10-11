<?php
declare(strict_types=1);

namespace App\lib;

class Util
{
    public static function env(string $key, string $default = ''): string
    {
        // Why use $_ENV instead of getenv()?
        // https://stackoverflow.com/questions/63307446/setup-file-env-in-slim-framework
        return $_ENV[strtoupper($key)] ?? $default;
    }
}
