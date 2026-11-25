<?php

/**
 * Starts the PHP session with secure cookie flags.
 *
 * Every endpoint that uses sessions runs this first instead of calling
 * session_start() directly. This keeps the security settings in one place.
 *
 * Why each flag matters:
 * - httponly: JavaScript cannot read the cookie, blocking XSS theft.
 * - samesite Lax: the cookie is not sent on cross-site POST requests,
 *   which stops most CSRF attacks while still allowing normal links.
 * - secure: only sent over HTTPS. Turned off for localhost development.
 */
class SessionMiddleware
{
    /** Inactivity limit in seconds (30 minutes). */
    private const TIMEOUT_SECONDS = 1800;

    /**
     * Starts the session once per request and applies the inactivity timeout.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Detect whether the request arrived over HTTPS.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // If the last activity was more than 30 minutes ago, destroy and restart.
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::TIMEOUT_SECONDS) {
            session_unset();
            session_destroy();
            session_start();
        }

        $_SESSION['last_activity'] = time();
    }
}
