<?php

/**
 * Creates accounts, checks passwords and manages the login session.
 *
 * Virtual identity: a user's identity is a row in `users` with a bcrypt hash.
 * After login the session holds the user id; logout destroys it.
 */
class AuthService
{
    /**
     * Inserts a new user with a bcrypt-hashed password.
     * Returns false if the username is already taken.
     *
     * Why bcrypt (PASSWORD_DEFAULT): it is slow on purpose, which makes
     * brute-force attacks expensive. PHP upgrades the algorithm automatically
     * when a stronger one becomes the default.
     */
    public static function createUser(string $username, string $password): bool
    {
        $db = Database::getConnection();

        $check = $db->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);

        if ($check->fetch()) {
            return false;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $insert->execute([$username, $hash]);

        return true;
    }

    /**
     * Returns the user row when username and password match, otherwise null.
     *
     * A wrong username and a wrong password both return null, so the caller
     * shows the same message and attackers cannot discover which usernames exist.
     */
    public static function verifyUser(string $username, string $password): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // password_verify compares the plain input against the bcrypt hash.
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    /**
     * Binds the user to the session after a successful login.
     * session_regenerate_id(true) issues a fresh id and deletes the old one,
     * which blocks session fixation attacks.
     */
    public static function startUserSession(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
    }

    /**
     * Clears session data, deletes the session cookie and destroys the
     * server-side session file, so the old session id cannot be reused.
     */
    public static function endUserSession(): void
    {
        $_SESSION = [];

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

        session_destroy();
    }
}
