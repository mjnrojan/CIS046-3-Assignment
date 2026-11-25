<?php

/**
 * Small helpers for reading the incoming HTTP request.
 *
 * Why: every endpoint used to repeat file_get_contents('php://input') +
 * json_decode. One helper keeps the JSON contract in one place.
 */
class Request
{
    /**
     * Returns the HTTP method, e.g. "GET" or "POST".
     */
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Decodes the JSON request body.
     * Returns an array, or null when the body is missing or not a JSON object/array.
     */
    public static function json(): ?array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        return is_array($data) ? $data : null;
    }
}
