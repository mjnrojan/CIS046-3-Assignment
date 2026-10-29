/**
 * Main game loop and state management.
 *
 * This file connects the player, level and input systems.
 * It runs a loop with requestAnimationFrame, which fires roughly
 * 60 times per second. Each frame: read input → update physics →
 * check interactions → draw everything.
 */

// --- Game state ---

var canvas;
var ctx;
var GRAVITY = 0.4;
var currentLevelIndex = 0;
var currentLevel = null;
var player = null;
var paused = false;
var keys = {};

// Scoring state for the current level.
var levelScore = 0;
var lives = 3;
var totalScrolls = 0;
var solvedScrolls = 0;

// Hint inventory persists across levels during the session.
var hints = { bronze: 0, silver: 0, gold: 0 };

// Camera position for side-scrolling.
var cameraX = 0;

/**
 * Starts or restarts a level by index.
 * Copies the level array so the original data is not modified
 * (scrolls and chests get removed during play).
 */
function loadLevel(index) {
    if (index >= LEVELS.length) {
        // All levels complete — show a win message.
        paused = true;
        showMessage('You completed all levels! Final score: ' + levelScore);
        return;
    }

    currentLevelIndex = index;

    // Deep copy so replaying a level starts fresh.
    currentLevel = LEVELS[index].map(function (row) {
        return row.slice();
    });

    var spawn = findSpawn(currentLevel);
    player = createPlayer(spawn.x, spawn.y);

    levelScore = 0;
    lives = 3;
    totalScrolls = countScrolls(currentLevel);
    solvedScrolls = 0;
    paused = false;
    cameraX = 0;

    hideMessage();
    hidePuzzleModal();

    updateHUD();
}

/**
 * Restarts the current level (called when lives reach 0).
 * Hints are kept because the player earned them fairly.
 */
function restartLevel() {
    loadLevel(currentLevelIndex);
}

// --- Keyboard input ---

/**
 * Tracks which keys are currently held down.
 * The game loop reads this object each frame instead of reacting
 * to individual key events. This gives smooth, consistent movement.
 */
function setupInput() {
    document.addEventListener('keydown', function (event) {
        keys[event.key] = true;
    });

    document.addEventListener('keyup', function (event) {
        keys[event.key] = false;
    });
}

// --- Object interactions ---

/**
 * Checks what tile the player is touching and reacts.
 * Scrolls trigger the puzzle, chests give hints, the door advances.
 * Each interaction fires a CustomEvent for loose coupling.
 */
function checkInteractions() {
    var tile = getPlayerTile(player, currentLevel);

    // Scroll — open the puzzle modal.
    if (tile === 2) {
        paused = true;
        removeTileAtPlayer(player, currentLevel);
        document.dispatchEvent(new CustomEvent('puzzleTriggered'));
        openPuzzleModal();
    }

    // Bronze chest — vague hint.
    if (tile === 4) {
        removeTileAtPlayer(player, currentLevel);
        hints.bronze = hints.bronze + 1;
        document.dispatchEvent(new CustomEvent('hintCollected', {
            detail: { tier: 'bronze' }
        }));
        showMessage('Found a Bronze chest! Hint saved. (Bronze: ' + hints.bronze + ')');
        updateHUD();
    }

    // Silver chest — narrowing hint.
    if (tile === 6) {
        removeTileAtPlayer(player, currentLevel);
        hints.silver = hints.silver + 1;
        document.dispatchEvent(new CustomEvent('hintCollected', {
            detail: { tier: 'silver' }
        }));
        showMessage('Found a Silver chest! Hint saved. (Silver: ' + hints.silver + ')');
        updateHUD();
    }

    // Gold chest — strong hint.
    if (tile === 7) {
        removeTileAtPlayer(player, currentLevel);
        hints.gold = hints.gold + 1;
        document.dispatchEvent(new CustomEvent('hintCollected', {
            detail: { tier: 'gold' }
        }));
        showMessage('Found a Gold chest! Hint saved. (Gold: ' + hints.gold + ')');
        updateHUD();
    }

    // Door — advance if all scrolls are solved.
    if (tile === 3 && solvedScrolls >= totalScrolls) {
        levelScore = levelScore + 200;
        document.dispatchEvent(new CustomEvent('levelComplete', {
            detail: { level: currentLevelIndex, score: levelScore }
        }));
        showMessage('Level complete! +200 bonus. Score: ' + levelScore);
        paused = true;

        // Load next level after a short delay.
        setTimeout(function () {
            loadLevel(currentLevelIndex + 1);
        }, 2000);
    }
}

/**
 * Called when the player solves a puzzle correctly.
 * Increments the solved count and awards points based on speed.
 */
function onPuzzleSolved(timeTaken) {
    solvedScrolls = solvedScrolls + 1;

    // Base score.
    var points = 100;

    // Speed bonus.
    if (timeTaken <= 5) {
        points = points + 50;
    } else if (timeTaken <= 10) {
        points = points + 25;
    } else if (timeTaken > 30) {
        points = points - 10;
    }

    levelScore = levelScore + points;
    paused = false;
    hidePuzzleModal();

    var remaining = totalScrolls - solvedScrolls;
    if (remaining > 0) {
        showMessage('Correct! +' + points + ' pts. ' + remaining + ' scroll(s) left.');
    } else {
        showMessage('All scrolls solved! Find the door.');
    }

    document.dispatchEvent(new CustomEvent('puzzleSolved', {
        detail: { points: points, remaining: remaining }
    }));

    updateHUD();
}

/**
 * Called when the player gives a wrong answer.
 * Deducts points and a life. At 0 lives the level restarts.
 * When lives remain, loads a fresh puzzle because the server
 * unsets the solution after every guess to prevent brute-forcing.
 */
function onPuzzleWrong() {
    levelScore = levelScore - 25;
    lives = lives - 1;

    if (lives <= 0) {
        hidePuzzleModal();
        showMessage('Out of lives! Restarting level...');
        setTimeout(function () {
            restartLevel();
        }, 1500);
        return;
    }

    var modalMsg = document.getElementById('modal-message');
    if (modalMsg) {
        modalMsg.textContent = 'Wrong answer (-25 pts). Loading new puzzle...';
    }

    showMessage('Wrong! -25 pts. Lives: ' + lives);
    updateHUD();

    // Fetch a new puzzle so PHP session stores a fresh solution for the next guess.
    loadPuzzleInModal();
}

// --- Camera ---

/**
 * Updates the camera to follow the player horizontally.
 * The camera centres on the player but stops at level edges
 * so you never see past the start or end of the level.
 */
function updateCamera() {
    var levelWidth = currentLevel[0].length * TILE;
    var halfCanvas = canvas.width / 2;

    cameraX = player.x - halfCanvas + player.width / 2;

    if (cameraX < 0) {
        cameraX = 0;
    }
    if (cameraX > levelWidth - canvas.width) {
        cameraX = levelWidth - canvas.width;
    }
}

// --- HUD ---

/**
 * Updates the on-screen HUD elements.
 * Score, lives and scroll count are shown outside the canvas
 * in HTML elements for simplicity and accessibility.
 */
function updateHUD() {
    var scoreEl = document.getElementById('hud-score');
    var livesEl = document.getElementById('hud-lives');
    var scrollsEl = document.getElementById('hud-scrolls');
    var hintsEl = document.getElementById('hud-hints');

    if (scoreEl) {
        scoreEl.textContent = 'Score: ' + levelScore;
    }
    if (livesEl) {
        livesEl.textContent = 'Lives: ' + '❤️'.repeat(lives);
    }
    if (scrollsEl) {
        scrollsEl.textContent = 'Scrolls: ' + solvedScrolls + ' / ' + totalScrolls;
    }
    if (hintsEl) {
        hintsEl.textContent = 'Hints — Bronze: ' + hints.bronze + ' Silver: ' + hints.silver + ' Gold: ' + hints.gold;
    }
}

// --- UI helpers ---

/**
 * Shows a temporary message below the canvas.
 */
function showMessage(text) {
    var el = document.getElementById('game-message');
    if (el) {
        el.textContent = text;
    }
}

function hideMessage() {
    showMessage('');
}

/**
 * Shows the puzzle modal overlay.
 */
function openPuzzleModal() {
    var modal = document.getElementById('puzzle-modal');
    if (modal) {
        modal.hidden = false;
    }
    loadPuzzleInModal();
}

function hidePuzzleModal() {
    var modal = document.getElementById('puzzle-modal');
    if (modal) {
        modal.hidden = true;
    }
}

/**
 * Fetches a puzzle from the API and displays it in the modal.
 * The timer starts when the image loads.
 */
var puzzleStartTime = 0;

function loadPuzzleInModal() {
    var img = document.getElementById('modal-puzzle-img');
    var msg = document.getElementById('modal-message');

    if (msg) {
        msg.textContent = 'Loading puzzle...';
    }

    getPuzzle()
        .then(function (url) {
            img.src = url;
            img.alt = 'Banana puzzle';
            puzzleStartTime = Date.now();
            if (msg) {
                msg.textContent = '';
            }
        })
        .catch(function (error) {
            if (msg) {
                msg.textContent = error.message;
            }
        });
}

// --- Drawing ---

/**
 * Draws the sky background.
 */
function drawBackground(ctx) {
    ctx.fillStyle = '#87CEEB';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
}

/**
 * Draws the door as green when unlocked (all scrolls solved).
 */
function drawDoorState(ctx, level, cameraX) {
    if (solvedScrolls < totalScrolls) {
        return;
    }

    for (var row = 0; row < level.length; row++) {
        for (var col = 0; col < level[row].length; col++) {
            if (level[row][col] === 3) {
                var screenX = col * TILE - cameraX;
                var screenY = row * TILE;
                ctx.fillStyle = '#27ae60';
                ctx.fillRect(screenX + 4, screenY, TILE - 8, TILE);
                ctx.fillStyle = '#f1c40f';
                ctx.fillRect(screenX + 20, screenY + 14, 4, 4);
            }
        }
    }
}

// --- Game loop ---

/**
 * The main game loop called by requestAnimationFrame.
 *
 * requestAnimationFrame is preferred over setInterval because:
 * - It syncs with the monitor refresh rate (usually 60fps).
 * - It pauses when the tab is not visible, saving CPU.
 * - The browser optimises the drawing schedule.
 */
function gameLoop() {
    if (!paused) {
        updatePlayer(player, keys, GRAVITY);
        resolveCollisions(player, currentLevel);
        checkInteractions();
        updateCamera();
    }

    // Always draw, even when paused, so the screen is not blank.
    drawBackground(ctx);
    drawLevel(ctx, currentLevel, cameraX, canvas.width);
    drawDoorState(ctx, currentLevel, cameraX);
    drawPlayer(ctx, player, cameraX);

    requestAnimationFrame(gameLoop);
}

// --- Initialisation ---

/**
 * Sets up the canvas, input and starts the first level.
 * Called once when game.html loads.
 */
function initGame(startLevel) {
    canvas = document.getElementById('game-canvas');
    ctx = canvas.getContext('2d');

    canvas.width = 800;
    canvas.height = 480;

    setupInput();
    setupModalEvents();
    loadLevel(startLevel || 0);
    requestAnimationFrame(gameLoop);
}

/**
 * Attaches events to the puzzle modal form.
 * Disables the submit button while a request is in flight to prevent
 * accidental double submits which cause 'No puzzle to answer' errors.
 */
var isSubmittingGuess = false;

function setupModalEvents() {
    var form = document.getElementById('modal-answer-form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (isSubmittingGuess) {
            return;
        }

        var input = document.getElementById('modal-guess');
        var guessText = input.value;

        if (!/^[0-9]$/.test(guessText)) {
            document.getElementById('modal-message').textContent = 'Enter 0-9.';
            return;
        }

        isSubmittingGuess = true;
        var submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        submitAnswer(Number(guessText))
            .then(function (correct) {
                if (correct) {
                    var timeTaken = (Date.now() - puzzleStartTime) / 1000;
                    onPuzzleSolved(timeTaken);
                } else {
                    onPuzzleWrong();
                }
                input.value = '';
            })
            .catch(function (error) {
                document.getElementById('modal-message').textContent = error.message;
            })
            .finally(function () {
                isSubmittingGuess = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
    });
}

// Start when the page loads.
window.addEventListener('load', function () {
    // Read level from URL: game.html?level=2
    var params = new URLSearchParams(window.location.search);
    var startLevel = parseInt(params.get('level'), 10) || 0;
    initGame(startLevel);
});
