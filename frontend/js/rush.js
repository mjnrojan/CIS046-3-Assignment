/**
 * Puzzle Rush client controller.
 *
 * Implements high-speed countdown timer, strike tracking (3 strikes max,
 * or 1 strike in Survival mode), rapid sub-5ms puzzle preloading from cache,
 * and game-over score submission to /api/rush.php.
 * Sound feedback is added via sound.js (must be loaded before this file).
 */

var durationSeconds = 300; // Default 5 minutes
var timeRemaining = 300;
var isSurvivalMode = false;
var timerInterval = null;

var solvedCount = 0;
var strikeCount = 0;
var isGameOver = false;
var isCheckingGuess = false;
var currentModeKey = '5m';

/**
 * Parses URL query parameters to configure the selected format.
 * Examples: rush.html?duration=60 or rush.html?mode=survival
 */
function configureRushMode() {
    var params = new URLSearchParams(window.location.search);
    var modeParam = params.get('mode');
    var durationParam = parseInt(params.get('duration'), 10);

    var modeLabel = document.getElementById('rush-mode-label');
    var timerEl = document.getElementById('rush-timer');

    if (modeParam === 'survival') {
        isSurvivalMode = true;
        currentModeKey = 'survival';
        if (modeLabel) modeLabel.textContent = '💀 Survival Mode';
        if (timerEl) timerEl.textContent = 'SURVIVAL';

        // In survival only 1 strike is allowed — hide extra indicators
        var s2 = document.getElementById('strike-2');
        var s3 = document.getElementById('strike-3');
        if (s2) s2.style.display = 'none';
        if (s3) s3.style.display = 'none';
    } else {
        if (!isNaN(durationParam) && [60, 300, 600].indexOf(durationParam) !== -1) {
            durationSeconds = durationParam;
        } else {
            durationSeconds = 300;
        }

        timeRemaining = durationSeconds;
        currentModeKey = durationSeconds === 60 ? '1m' : (durationSeconds === 600 ? '10m' : '5m');

        if (modeLabel) {
            modeLabel.textContent = '⚡ ' + (durationSeconds / 60) + '-Minute Rush';
        }
        updateTimerDisplay();
    }
}

/**
 * Formats and updates the countdown timer display.
 */
function updateTimerDisplay() {
    if (isSurvivalMode) return;

    var timerEl = document.getElementById('rush-timer');
    if (!timerEl) return;

    var minutes = Math.floor(timeRemaining / 60);
    var seconds = timeRemaining % 60;
    timerEl.textContent = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

    if (timeRemaining <= 30) {
        timerEl.classList.add('urgent');
    } else {
        timerEl.classList.remove('urgent');
    }
}

/**
 * Ticks the countdown timer every second.
 * Calls endRushGame when time runs out.
 */
function startRushTimer() {
    if (isSurvivalMode) return;

    timerInterval = setInterval(function () {
        if (isGameOver) {
            clearInterval(timerInterval);
            return;
        }

        timeRemaining--;
        updateTimerDisplay();

        if (timeRemaining <= 0) {
            endRushGame('Time expired!');
        }
    }, 1000);
}

/**
 * Loads user profile to show all-time high score in header.
 */
function loadUserHighScore() {
    fetch('/api/me.php', { credentials: 'same-origin' })
        .then(function (res) {
            if (res.status === 401) {
                window.location.href = 'login.html';
                return null;
            }
            return res.json();
        })
        .then(function (data) {
            if (data && document.getElementById('header-high-score')) {
                document.getElementById('header-high-score').textContent = data.rush_high_score;
            }
        });
}

/**
 * Loads a fresh puzzle instantly from the low-latency session cache.
 * Shows a loading tip on first puzzle load so player has a fact while getting ready.
 */
function loadNextRushPuzzle() {
    if (isGameOver) return;

    var img = document.getElementById('rush-puzzle-img');
    var input = document.getElementById('rush-input');

    if (solvedCount === 0 && typeof showLoadingTip === 'function') {
        showLoadingTip('⚡ Rush Tip');
    }

    getPuzzle()
        .then(function (url) {
            img.src = url;
            if (input) {
                input.value = '';
                input.focus();
            }
        })
        .catch(function (err) {
            console.error('Puzzle load error:', err);
        });
}

/**
 * Ends the run, plays a game-over sound, submits final score to
 * /api/rush.php, and displays the summary modal.
 */
function endRushGame(reason) {
    if (isGameOver) return;
    isGameOver = true;

    if (timerInterval) {
        clearInterval(timerInterval);
    }

    var input = document.getElementById('rush-input');
    var submitBtn = document.getElementById('btn-rush-submit');
    if (input) input.disabled = true;
    if (submitBtn) submitBtn.disabled = true;

    // Audio: game-over sting when the run ends
    if (typeof playSound === 'function') { playSound('gameover'); }

    // Send completed run to backend
    fetch('/api/rush.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            solved: solvedCount,
            mode: currentModeKey,
            strikes: strikeCount
        })
    })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            var modal = document.getElementById('rush-summary-modal');
            var scoreVal = document.getElementById('summary-score-val');
            var highBadge = document.getElementById('summary-high-badge');

            if (scoreVal) scoreVal.textContent = solvedCount;
            if (highBadge && result.is_new_high_score) {
                highBadge.hidden = false;
            }
            if (modal) modal.hidden = false;
        })
        .catch(function (err) {
            console.error('Error saving rush score:', err);
        });
}

/**
 * Attaches form submit and answer evaluation listeners.
 * Sound is played immediately on submit (click), then again on
 * correct (solve) or wrong (strike / gameover) outcome.
 */
function setupRushEvents() {
    var form = document.getElementById('rush-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (isGameOver || isCheckingGuess) return;

        var input = document.getElementById('rush-input');
        var feedback = document.getElementById('rush-feedback');
        var guessText = input.value.trim();

        if (!/^[0-9]$/.test(guessText)) {
            return;
        }

        // Audio: click sound the moment the player submits
        if (typeof playSound === 'function') { playSound('click'); }

        isCheckingGuess = true;

        submitAnswer(Number(guessText))
            .then(function (correct) {
                if (correct) {
                    // Correct: increment solve counter and play solve sound
                    solvedCount++;
                    document.getElementById('rush-solve-count').textContent = solvedCount;

                    if (feedback) {
                        feedback.textContent = '✓ Correct!';
                        feedback.className = 'rush-feedback correct';
                    }

                    // Audio: quick positive ding on a correct solve
                    if (typeof playSound === 'function') { playSound('solve'); }

                    // Toast: show affirmation + math fact for solved digit
                    if (typeof fetchAndShowToast === 'function') {
                        fetchAndShowToast(Number(guessText), '✓ Correct!');
                    }

                    loadNextRushPuzzle();
                } else {
                    // Wrong: add a strike and play strike buzz
                    strikeCount++;

                    if (feedback) {
                        feedback.textContent = '✗ Strike ' + strikeCount + ' / 3!';
                        feedback.className = 'rush-feedback wrong';
                    }

                    // Audio: buzz on a wrong answer
                    if (typeof playSound === 'function') { playSound('strike'); }

                    // Toast: show encouragement toast on strike
                    if (typeof fetchAndShowToast === 'function') {
                        fetchAndShowToast(Number(guessText), '⚡ Strike ' + strikeCount + ' / 3');
                    }

                    var strikeBox = document.getElementById('strike-' + strikeCount);
                    if (strikeBox) {
                        strikeBox.classList.add('active');
                        strikeBox.textContent = '✕';
                    }

                    // gameover sound handled inside endRushGame
                    if (isSurvivalMode || strikeCount >= 3) {
                        endRushGame('3 Strikes accumulated!');
                    } else {
                        loadNextRushPuzzle();
                    }
                }
            })
            .catch(function () {
                if (feedback) {
                    feedback.textContent = 'Could not submit answer. Check connection.';
                    feedback.className = 'rush-feedback wrong';
                }
            })
            .finally(function () {
                isCheckingGuess = false;
            });
    });

    // Replay button in summary modal reloads the page to restart
    var replayBtn = document.getElementById('btn-play-again');
    if (replayBtn) {
        replayBtn.addEventListener('click', function () {
            window.location.reload();
        });
    }
}

// Start game when page loads
window.addEventListener('load', function () {
    configureRushMode();
    loadUserHighScore();
    setupRushEvents();
    loadNextRushPuzzle();
    startRushTimer();
});
