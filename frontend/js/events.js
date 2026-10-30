/**
 * Custom event subscribers for the Banana game.
 *
 * This file implements the Observer (Publish-Subscribe) pattern.
 * The game loop in game.js publishes CustomEvents when state changes
 * (puzzle triggered, puzzle solved, hint collected, level complete).
 *
 * This file listens for those events and coordinates secondary actions
 * like sound effects, UI flash animations, and logging, without the
 * physics or collision code needing to know about them.
 */

/**
 * Handles the 'puzzleTriggered' event.
 * Fired when the player collides with a scroll tile.
 */
function handlePuzzleTriggered(event) {
    // Focus the modal guess input after a tiny tick so the player can type immediately.
    setTimeout(function () {
        var input = document.getElementById('modal-guess');
        if (input) {
            input.focus();
        }
    }, 50);

    // Announce to screen readers that a challenge has begun.
    var messageEl = document.getElementById('game-message');
    if (messageEl) {
        messageEl.setAttribute('aria-live', 'assertive');
    }
}

/**
 * Handles the 'puzzleSolved' event.
 * Detail object contains: { points, remaining }.
 */
function handlePuzzleSolved(event) {
    var detail = event.detail || {};
    var points = detail.points || 0;
    var remaining = detail.remaining || 0;

    // Visual feedback: briefly highlight the score element.
    var scoreEl = document.getElementById('hud-score');
    if (scoreEl) {
        scoreEl.style.transition = 'color 0.2s';
        scoreEl.style.color = '#2ecc71';
        setTimeout(function () {
            scoreEl.style.color = '';
        }, 600);
    }
}

/**
 * Handles the 'hintCollected' event.
 * Detail object contains: { tier } ('bronze', 'silver', 'gold').
 */
function handleHintCollected(event) {
    var detail = event.detail || {};
    var tier = detail.tier || 'bronze';

    // Highlight the hint inventory on HUD.
    var hintsEl = document.getElementById('hud-hints');
    if (hintsEl) {
        hintsEl.style.transition = 'background-color 0.3s';
        hintsEl.style.backgroundColor = 'rgba(241, 196, 15, 0.25)';
        setTimeout(function () {
            hintsEl.style.backgroundColor = '';
        }, 700);
    }
}

/**
 * Handles the 'levelComplete' event.
 * Detail object contains: { level, score }.
 */
function handleLevelComplete(event) {
    var detail = event.detail || {};
    var level = detail.level;
    var score = detail.score;

    // Pulse the game message to celebrate.
    var msgEl = document.getElementById('game-message');
    if (msgEl) {
        msgEl.style.fontSize = '1.2rem';
        setTimeout(function () {
            msgEl.style.fontSize = '';
        }, 1800);
    }
}

/**
 * Attaches all custom event listeners to the document.
 * Called when the page scripts initialize.
 */
function listenForGameEvents() {
    document.addEventListener('puzzleTriggered', handlePuzzleTriggered);
    document.addEventListener('puzzleSolved', handlePuzzleSolved);
    document.addEventListener('hintCollected', handleHintCollected);
    document.addEventListener('levelComplete', handleLevelComplete);
}

// Register listeners on load.
listenForGameEvents();
