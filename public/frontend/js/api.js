/**
 * Loads one puzzle from our PHP API.
 * The answer stays in the PHP session, so this function returns only the image URL.
 */
async function getPuzzle() {
    const response = await fetch('/api/puzzle.php', {
        credentials: 'same-origin'
    });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || 'Could not load the puzzle');
    }

    return data.question;
}

/**
 * Sends one guess to the answer endpoint.
 * The session cookie from getPuzzle must go with this request, or PHP has no answer to check.
 */
async function submitAnswer(guess) {
    const response = await fetch('/api/answer.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ guess: guess })
    });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || 'Could not submit the guess');
    }

    return data.correct;
}
