<?php

require_once __DIR__ . '/../src/session.php';

startSecureSession();

header('Content-Type: application/json');

require_once __DIR__ . '/../src/Database.php';

/**
 * Allows POST only.
 * Registration creates a resource, so GET is not appropriate.
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
 * Returns them as an array, or null if either is missing or too short.
 * Username must be 3-50 characters, password at least 6.
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

    if (strlen($username) < 3 || strlen($username) > 50) {
        return null;
    }

    if (strlen($password) < 6) {
        return null;
    }

    return ['username' => $username, 'password' => $password];
}

/**
 * Inserts a new user row with a bcrypt-hashed password.
 * Returns true on success, false if the username already exists.
 *
 * Why bcrypt (PASSWORD_DEFAULT): it is slow on purpose, which makes
 * brute-force attacks expensive. PHP upgrades the algorithm automatically
 * when a stronger one becomes the default.
 */
function createUser(string $username, string $password): bool
{
    $db = Database::getConnection();

    // Check if username is already taken.
    $check = $db->prepare('SELECT id FROM users WHERE username = ?');
    $check->execute([$username]);

    if ($check->fetch()) {
        return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $db->prepare(
        'INSERT INTO users (username, password_hash) VALUES (?, ?)'
    );
    $insert->execute([$username, $hash]);

    return true;
}

// --- Main flow ---

rejectUnlessPost();

$credentials = readCredentials();

if ($credentials === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Username (3-50 chars) and password (6+ chars) are required']);
    exit;
}

$created = createUser($credentials['username'], $credentials['password']);

if (!$created) {
    http_response_code(409);
    echo json_encode(['error' => 'Username already taken']);
    exit;
}

http_response_code(201);
echo json_encode(['message' => 'Account created']);
