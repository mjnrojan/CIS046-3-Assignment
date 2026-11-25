<?php

/**
 * HTTP handlers for Classic Ladder mode.
 * Wrong guesses allow unlimited retries on the same puzzle but lower the reward.
 */
class LadderController
{
    /**
     * GET /api/ladder.php — current node, points and the reward still available.
     */
    public static function show(int $userId): void
    {
        $user = LadderService::getUser($userId);
        $attempts = isset($_SESSION['ladder_attempts']) ? (int) $_SESSION['ladder_attempts'] : 0;

        Response::json([
            'ladder_node'      => $user['ladder_node'],
            'ladder_points'    => $user['ladder_points'],
            'attempts'         => $attempts,
            'potential_points' => LadderService::potentialPoints($attempts),
        ]);
    }

    /**
     * POST /api/ladder.php  {"guess": 0-9}
     * Wrong: counts the attempt. Correct: awards points and advances the node.
     */
    public static function guess(int $userId): void
    {
        $data = Request::json();

        if ($data === null || !isset($data['guess']) || !preg_match('/^[0-9]$/', (string) $data['guess'])) {
            Response::error('Guess must be an integer from 0 to 9', 400);
        }

        if (!isset($_SESSION['solution'])) {
            Response::error('No active puzzle to answer. Load a puzzle first.', 400);
        }

        $guess = (int) $data['guess'];
        $expected = (int) $_SESSION['solution'];

        if (!isset($_SESSION['ladder_attempts'])) {
            $_SESSION['ladder_attempts'] = 0;
        }

        if ($guess !== $expected) {
            $_SESSION['ladder_attempts']++;
            $attempts = $_SESSION['ladder_attempts'];

            Response::json([
                'correct'          => false,
                'message'          => 'Incorrect guess. Try again!',
                'attempts'         => $attempts,
                'potential_points' => LadderService::potentialPoints($attempts),
            ]);
        }

        $pointsEarned = LadderService::potentialPoints($_SESSION['ladder_attempts']);
        $updatedUser = LadderService::recordSolve($userId, $pointsEarned);

        // Reset attempts and consume the puzzle.
        unset($_SESSION['solution'], $_SESSION['issued_at']);
        $_SESSION['ladder_attempts'] = 0;

        Response::json([
            'correct'             => true,
            'message'             => 'Puzzle Solved!',
            'points_earned'       => $pointsEarned,
            'total_ladder_points' => $updatedUser['ladder_points'],
            'next_node'           => $updatedUser['ladder_node'],
        ]);
    }
}
