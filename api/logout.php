<?php

session_start();

header('Content-Type: application/json');

/**
 * Allows POST only.
 * Logout changes server state (destroys a session), so GET is wrong.
 * A GET request could be triggered by a link or an image tag,
 * which would log the user out without their intent (CSRF risk).
 */
function rejectUnlessPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }

    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

rejectUnlessPost();

// Clear all session data from memory.
$_SESSION = [];

// Delete the session cookie from the browser.
// This ensures the old session ID cannot be reused.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session file on the server.
session_destroy();

echo json_encode(['message' => 'Logged out']);
