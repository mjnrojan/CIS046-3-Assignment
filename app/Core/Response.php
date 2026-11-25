<?php

/**
 * Sends JSON responses.
 *
 * Why: every endpoint repeated http_response_code + header + echo json_encode + exit.
 * One helper guarantees every response uses the same JSON content type,
 * which is the data contract the JavaScript frontend relies on.
 */
class Response
{
    /**
     * Sends $data as JSON with the given status code, then stops the script.
     * $flags are passed to json_encode (e.g. JSON_UNESCAPED_SLASHES for URLs).
     */
    public static function json(array $data, int $status = 200, int $flags = 0): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, $flags);
        exit;
    }

    /**
     * Sends {"error": "..."} with the given status code, then stops.
     */
    public static function error(string $message, int $status): void
    {
        self::json(['error' => $message], $status);
    }
}
