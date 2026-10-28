<?php

/**
 * Configures and starts the session with secure cookie flags.
 *
 * Every PHP file that uses sessions should require this file
 * instead of calling session_start() directly. This keeps the
 * security settings in one place.
 *
 * Why each flag matters:
 * - httponly: JavaScript cannot read the cookie, blocking XSS theft.
 * - samesite Lax: the cookie is not sent on cross-site POST requests,
 *   which stops most CSRF attacks while still allowing normal links.
 * - secure: only sent over HTTPS. Turned off for localhost development.
 */
function startSecureSession(): void
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
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Session timeout: 30 minutes of inactivity.
    // If the last activity was more than 30 minutes ago, destroy and restart.
    $timeout = 1800;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        session_start();
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Checks that a user is logged in.
 * Returns the user_id if logged in, or sends a 401 response and exits.
 *
 * Any endpoint that needs a logged-in user calls this at the top.
 * This is authorization: you proved your identity at login,
 * now we check the session still holds that proof.
 */
function requireLogin(): int
{
    if (isset($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'You must be logged in']);
    exit;
}
