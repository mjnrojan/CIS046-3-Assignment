<?php

/**
 * Rejects HTTP methods an endpoint does not support.
 *
 * Why: state-changing actions (login, answer, logout) must be POST so a link
 * or <img> tag cannot trigger them (CSRF risk). Reads (leaderboard) are GET.
 * Before the refactor each endpoint had its own copy of rejectUnlessPost().
 */
class MethodMiddleware
{
    /**
     * Continues if the request method is one of $allowed,
     * otherwise sends 405 with an Allow header and stops.
     */
    public static function allow(string ...$allowed): void
    {
        if (in_array(Request::method(), $allowed, true)) {
            return;
        }

        header('Allow: ' . implode(', ', $allowed));
        Response::error('Method not allowed', 405);
    }
}
