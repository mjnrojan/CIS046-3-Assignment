let requestInProgress = false;

/**
 * Puts a new puzzle image on the page.
 * keepMessage is true after an answer so "Correct." or "Wrong." stays visible.
 */
async function loadPuzzle(keepMessage) {
    const image = document.getElementById('puzzle');
    const message = document.getElementById('message');

    if (!keepMessage) {
        message.textContent = 'Loading puzzle...';
    }

    try {
        const url = await getPuzzle();
        image.src = url;
        image.alt = 'Banana puzzle';

        if (!keepMessage) {
            message.textContent = '';
        }
    } catch (error) {
        message.textContent = error.message;
    }
}

/**
 * Checks the typed guess and then loads the next puzzle.
 * The button click and the Enter key both call this, so the checking logic is written once.
 */
async function sendGuess() {
    if (requestInProgress) {
        return;
    }

    const input = document.getElementById('guess');
    const message = document.getElementById('message');
    const guessText = input.value;

    if (!/^[0-9]$/.test(guessText)) {
        message.textContent = 'Enter a number from 0 to 9.';
        return;
    }

    requestInProgress = true;

    try {
        const correct = await submitAnswer(Number(guessText));
        message.textContent = correct ? 'Correct.' : 'Wrong.';
        input.value = '';
        await loadPuzzle(true);
    } catch (error) {
        message.textContent = error.message;
    }

    requestInProgress = false;
}

/**
 * Attaches the page events.
 * The load event fetches the image. Click and Enter both submit the guess.
 * The form submit event is cancelled so the browser does not reload the page.
 */
function listenForPuzzleEvents() {
    window.addEventListener('load', function () {
        loadPuzzle(false);
    });

    document.getElementById('answer-form').addEventListener('submit', function (event) {
        event.preventDefault();
    });

    document.querySelector('#answer-form button').addEventListener('click', function (event) {
        event.preventDefault();
        sendGuess();
    });

    document.getElementById('guess').addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        sendGuess();
    });
}

listenForPuzzleEvents();
