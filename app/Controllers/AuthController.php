<?php

/**
 * HTTP handlers for register, login, logout and the current profile.
 * Controllers only read the request, validate it and send a response;
 * the real work happens in AuthService and ProfileService.
 */
class AuthController
{
    /**
     * POST /api/register.php  {"username": "...", "password": "..."}
     * Username must be 3-50 characters, password at least 6.
     */
    public static function register(): void
    {
        $data = Request::json();
        $username = isset($data['username']) ? trim($data['username']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

        if ($data === null || strlen($username) < 3 || strlen($username) > 50 || strlen($password) < 6) {
            Response::error('Username (3-50 chars) and password (6+ chars) are required', 400);
        }

        if (!AuthService::createUser($username, $password)) {
            Response::error('Username already taken', 409);
        }

        Response::json(['message' => 'Account created'], 201);
    }

    /**
     * POST /api/login.php  {"username": "...", "password": "..."}
     * Wrong username and wrong password give the same 401 message.
     */
    public static function login(): void
    {
        $data = Request::json();
        $username = isset($data['username']) ? trim($data['username']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

        if ($data === null || $username === '' || $password === '') {
            Response::error('Username and password are required', 400);
        }

        $user = AuthService::verifyUser($username, $password);

        if ($user === null) {
            Response::error('Invalid username or password', 401);
        }

        AuthService::startUserSession($user);

        Response::json(['message' => 'Logged in', 'username' => $user['username']]);
    }

    /**
     * POST /api/logout.php
     */
    public static function logout(): void
    {
        AuthService::endUserSession();

        Response::json(['message' => 'Logged out']);
    }

    /**
     * GET /api/me.php — the logged-in player's profile and stats.
     * Database errors are logged on the server only, never sent to the browser.
     */
    public static function me(int $userId): void
    {
        try {
            $profile = ProfileService::find($userId);
        } catch (Exception $e) {
            error_log('[me.php] ' . $e->getMessage());
            Response::error('Could not load profile. Please try again.', 500);
        }

        if ($profile === null) {
            Response::error('User account not found', 404);
        }

        Response::json($profile);
    }
}
