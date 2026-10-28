<?php

/**
 * Gives the app a single shared PDO connection.
 *
 * Why a class instead of a plain function:
 * - The connection is created once and reused (singleton pattern).
 * - PDO is set to throw exceptions so errors surface immediately.
 * - Prepared statements are the only way to run queries, blocking SQL injection.
 */
class Database
{
    /** @var PDO|null Holds the one connection for the whole request. */
    private static ?PDO $connection = null;

    /**
     * Reads key=value pairs from the .env file at the project root.
     * This avoids needing a third-party library like vlucas/phpdotenv.
     * Lines starting with # and blank lines are skipped.
     */
    private static function loadEnv(): array
    {
        $path = __DIR__ . '/../.env';

        if (!file_exists($path)) {
            throw new RuntimeException('.env file not found. Copy .env.example to .env and fill in your values.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $vars = [];

        foreach ($lines as $line) {
            // Skip comments.
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Split on the first = sign only, so values can contain =.
            $parts = explode('=', $line, 2);

            if (count($parts) === 2) {
                $vars[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $vars;
    }

    /**
     * Returns the shared PDO connection.
     * Creates it on the first call, reuses it on every later call.
     *
     * Why singleton: opening a new connection on every query wastes time.
     * The trade-off is that unit tests are harder to isolate, but for a
     * small student project this is the simpler choice.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $env = self::loadEnv();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $env['DB_HOST'],
            $env['DB_PORT'],
            $env['DB_NAME'],
            $env['DB_CHARSET']
        );

        // PDO options:
        // ERRMODE_EXCEPTION — errors throw, so we never silently fail.
        // FETCH_ASSOC       — rows come back as arrays, not objects.
        // EMULATE_PREPARES off — the database does the real preparing,
        //   which is safer and catches type errors earlier.
        self::$connection = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$connection;
    }
}
