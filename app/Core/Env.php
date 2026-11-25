<?php

/**
 * Reads settings from the .env file at the project root.
 *
 * Why one class: before the refactor, Database.php, sounds.php and
 * sounds_preload.php each parsed .env with their own copy of the same loop.
 * Now there is one parser, loaded once per request and reused.
 * No third-party library (like vlucas/phpdotenv) is needed for KEY=value lines.
 */
class Env
{
    /** @var array<string,string>|null Parsed values, cached after the first read. */
    private static ?array $values = null;

    /**
     * Returns the full path to the .env file.
     */
    public static function path(): string
    {
        return __DIR__ . '/../../.env';
    }

    /**
     * Returns true when the .env file exists.
     */
    public static function fileExists(): bool
    {
        return file_exists(self::path());
    }

    /**
     * Returns one setting, or $default when it is missing.
     * Lines starting with # are comments. Values may contain "=".
     */
    public static function get(string $key, string $default = ''): string
    {
        if (self::$values === null) {
            self::$values = self::load();
        }

        return self::$values[$key] ?? $default;
    }

    /**
     * Parses KEY=value lines into an array. Returns [] if there is no .env file.
     */
    private static function load(): array
    {
        if (!self::fileExists()) {
            return [];
        }

        $vars = [];

        foreach (file(self::path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Split on the first = only, so values can contain =.
            $parts = explode('=', $line, 2);

            if (count($parts) === 2) {
                $vars[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $vars;
    }
}
