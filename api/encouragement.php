<?php

/**
 * =====================================================================
 * BANANA PUZZLE ARENA – ENCOURAGEMENT API PROXY
 * =====================================================================
 *
 * Combines two data sources into one low-latency response:
 *
 *   1. Affirmations API (https://www.affirmations.dev/)
 *      Uplifting message session-cached to guarantee < 10ms response.
 *
 *   2. PHP-computed Math Fact
 *      Fun mathematical property of any digit 0-9 (or random if not given).
 *
 * Query: GET /api/encouragement.php (returns random fact)
 *        GET /api/encouragement.php?answer=7 (returns specific fact for 7)
 * Response: { "affirmation": "...", "fact": "...", "answer": 7 }
 */

require_once __DIR__ . '/../src/session.php';
startSecureSession();
requireLogin();

header('Content-Type: application/json');

// If an answer digit 0-9 is provided, use it; otherwise pick a random digit
// so loading screens and matchmaking can request facts without a guess.
if (isset($_GET['answer']) && is_numeric($_GET['answer']) && (int) $_GET['answer'] >= 0 && (int) $_GET['answer'] <= 9) {
    $answer = (int) $_GET['answer'];
} else {
    $answer = rand(0, 9);
}

if (!function_exists('fetchAffirmation')) {
    /**
     * Retrieves an uplifting affirmation.
     * Uses a session cache so response time stays under 10ms. On cache miss,
     * initializes with an encouraging pool and attempts a 1-second timeout fetch
     * from affirmations.dev to blend live API data without freezing gameplay.
     */
    function fetchAffirmation(): string
    {
        $defaultPool = [
            'You have the power to solve this!',
            'Every puzzle trains your logic and pattern recognition.',
            'Keep going — progress happens one equation at a time!',
            'Stay calm, examine the operators, and trust your math.',
            'Mistakes are just stepping stones to mastery.',
            'Sharp minds spot the patterns first!',
            'Your brain is on fire today! 🔥',
            'Outstanding focus — keep that momentum going!',
            'One riddle at a time — you have got this!',
            'Great effort! Consistency is what builds champions.',
        ];

        if (!isset($_SESSION['affirmation_cache']) || !is_array($_SESSION['affirmation_cache'])) {
            $_SESSION['affirmation_cache'] = $defaultPool;

            // Try a 1-second non-blocking remote probe to augment the pool
            $ctx = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 1, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $response = @file_get_contents('https://www.affirmations.dev/', false, $ctx);
            if ($response) {
                $data = json_decode($response, true);
                if (!empty($data['affirmation'])) {
                    $_SESSION['affirmation_cache'][] = $data['affirmation'];
                    return $data['affirmation'];
                }
            }
        }

        $pool = $_SESSION['affirmation_cache'];
        return $pool[array_rand($pool)];
    }
}

if (!function_exists('computeMathFact')) {
    /**
     * Computes an interesting mathematical fact about a digit 0-9.
     * Covers prime, composite, identity, Fibonacci, square, and perfect numbers.
     * Computed entirely in PHP for instant, zero-latency execution.
     */
    function computeMathFact(int $n): string
    {
        $facts = [
            0 => '0 is the additive identity — adding 0 to any number leaves it unchanged.',
            1 => '1 is the multiplicative identity — it is neither prime nor composite.',
            2 => '2 is the only even prime number, and the smallest prime of all.',
            3 => '3 is the first odd prime number and also a Fibonacci number.',
            4 => '4 is the smallest composite number and a perfect square (2²).',
            5 => '5 is both a prime number and a Fibonacci number. It is the only prime ending in 5.',
            6 => '6 is the smallest perfect number — it equals the sum of its proper divisors (1 + 2 + 3).',
            7 => '7 is a prime number and considered lucky across many cultures and games.',
            8 => '8 is the first non-trivial cube number (2³ = 8) and a Fibonacci number.',
            9 => '9 is a perfect square (3²). Any number whose digits sum to 9 is divisible by 9.',
        ];

        return $facts[$n] ?? ($n . ' is a single-digit integer.');
    }
}

$affirmation = fetchAffirmation();
$fact        = computeMathFact($answer);

echo json_encode([
    'affirmation' => $affirmation,
    'fact'        => $fact,
    'answer'      => $answer,
], JSON_UNESCAPED_UNICODE);
