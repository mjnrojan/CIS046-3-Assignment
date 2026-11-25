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

        if (!Env::fileExists()) {
            throw new RuntimeException('.env file not found. Copy .env.example to .env and fill in your values.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            Env::get('DB_HOST'),
            Env::get('DB_PORT'),
            Env::get('DB_NAME'),
            Env::get('DB_CHARSET')
        );

        // PDO options:
        // ERRMODE_EXCEPTION — errors throw, so we never silently fail.
        // FETCH_ASSOC       — rows come back as arrays, not objects.
        // EMULATE_PREPARES off — the database does the real preparing,
        //   which is safer and catches type errors earlier.
        self::$connection = new PDO($dsn, Env::get('DB_USER'), Env::get('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$connection;
    }
}
