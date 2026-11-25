<?php

/**
 * Guards endpoints that need a logged-in user.
 *
 * This is authorization: the user proved their identity at login,
 * now each request checks the session still holds that proof.
 */
class AuthMiddleware
{
    /**
     * Returns the logged-in user's id, or sends 401 and stops the request.
     */
    public static function requireLogin(): int
    {
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        Response::error('You must be logged in', 401);
        return 0; // Never reached: Response::error() exits.
    }
}
