<?php

require_once __DIR__ . '/../src/session.php';

startSecureSession();

header('Content-Type: application/json');

require_once __DIR__ . '/../src/Database.php';

/**
 * Allows POST only.
 * Login is a state-changing action, so GET is not appropriate.
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

/**
 * Reads username and password from a JSON body.
 * Returns them as an array, or null if either is missing.
 */
function readCredentials(): ?array
{
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!is_array($data)) {
        return null;
    }

    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? $data['password'] : '';

    if ($username === '' || $password === '') {
        return null;
    }

    return ['username' => $username, 'password' => $password];
}

/**
 * Looks up the user and checks the password hash.
 * Returns the user row on success, null on failure.
 *
 * The same generic message is returned for a wrong username and
 * a wrong password. This prevents attackers from discovering
 * which usernames exist (enumeration attack).
 */
function verifyUser(string $username, string $password): ?array
{
    $db = Database::getConnection();

    $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // User not found — return null, same as wrong password.
    if (!$user) {
        return null;
    }

    // password_verify compares the plain input against the bcrypt hash.
    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

// --- Main flow ---

rejectUnlessPost();

$credentials = readCredentials();

if ($credentials === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

$user = verifyUser($credentials['username'], $credentials['password']);

if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

// Regenerate the session ID to prevent session fixation attacks.
// true deletes the old session file so it cannot be reused.
session_regenerate_id(true);

$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];

echo json_encode([
    'message'  => 'Logged in',
    'username' => $user['username'],
]);
