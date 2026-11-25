<?php

/**
 * Builds the encouragement toast: an affirmation plus a maths fact.
 *
 * The affirmation pool is cached in the session. On the first call it also
 * tries affirmations.dev with a 1-second timeout, so a slow third-party API
 * can never freeze gameplay. The maths facts are computed locally.
 */
class EncouragementService
{
    private const DEFAULT_POOL = [
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

    private const FACTS = [
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

    /**
     * Returns one affirmation. Fills the session pool on first use and
     * blends in one live affirmation from affirmations.dev when it responds in time.
     */
    public static function affirmation(): string
    {
        if (!isset($_SESSION['affirmation_cache']) || !is_array($_SESSION['affirmation_cache'])) {
            $_SESSION['affirmation_cache'] = self::DEFAULT_POOL;

            // verify_peer off for local development only; must be true in production.
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

    /**
     * Returns a maths fact about a digit 0-9.
     */
    public static function mathFact(int $n): string
    {
        return self::FACTS[$n] ?? ($n . ' is a single-digit integer.');
    }
}
