/**
 * =====================================================================
 * BANANA PUZZLE ARENA – 1v1 PUZZLE BATTLE CONTROLLER
 * =====================================================================
 *
 * Implements synchronous 3-minute duel logic:
 * - Match initialization and opponent pairing via /api/battle.php
 * - Head-to-head split HUD with live race bar tracking
 * - 3-strike state machine for both combatants
 * - Adaptive Challenger Bot opponent simulation
 * - Match resolution and Elo rating presentation modal
 */

var timeRemaining = 180; // 3 minutes
var matchTimer = null;
var opponentTimer = null;
var isBattleOver = false;
var isCheckingGuess = false;

var playerScore = 0;
var opponentScore = 0;
var playerStrikes = 0;
var opponentStrikes = 0;

var userRating = 500;
var opponentRating = 520;

/**
 * Initializes the battle by contacting the backend, binding match
 * metadata to the HUD, and starting the match clock.
 */
function initBattle() {
    fetch('/api/battle.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start' })
    })
        .then(function (res) {
            if (res.status === 401) {
                window.location.href = 'login.html';
                return null;
            }
            return res.json();
        })
        .then(function (data) {
            if (!data) return;

            // Bind player and opponent info to the HUD
            var pRatingEl = document.getElementById('player-rating-badge');
            var oRatingEl = document.getElementById('opponent-rating-badge');
            var oNameEl = document.getElementById('opponent-name');

            if (pRatingEl) pRatingEl.textContent = data.user_rating;
            if (oRatingEl) oRatingEl.textContent = data.opponent_rating;
            if (oNameEl) oNameEl.textContent = data.opponent_name;

            userRating = data.user_rating;
            opponentRating = data.opponent_rating;
            timeRemaining = data.duration || 180;

            updateTimerDisplay();
            startBattleTimer();
            startOpponentAI();
            loadNextBattlePuzzle();
        })
        .catch(function (err) {
            console.error('Error starting battle:', err);
        });
}

/**
 * Updates the countdown timer display in MM:SS format.
 */
function updateTimerDisplay() {
    var timerEl = document.getElementById('battle-timer');
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
 * Runs the 1-second countdown clock for the 3-minute match.
 */
function startBattleTimer() {
    matchTimer = setInterval(function () {
        if (isBattleOver) {
            clearInterval(matchTimer);
            return;
        }

        timeRemaining--;
        updateTimerDisplay();

        if (timeRemaining <= 0) {
            endBattle('Time expired!');
        }
    }, 1000);
}

/**
 * Simulates a realistic competitive Challenger Bot opponent.
 * Generates solve events every 7-14 seconds and occasional strikes.
 */
function startOpponentAI() {
    function scheduleNextAction() {
        if (isBattleOver) return;

        // Solve speed varies between 6,000ms and 13,000ms based on bot rating
        var delay = Math.floor(Math.random() * 6000) + 7000;

        opponentTimer = setTimeout(function () {
            if (isBattleOver) return;

            // 15% chance of opponent making a strike mistake
            var isMistake = Math.random() < 0.15;

            if (isMistake && opponentStrikes < 3) {
                opponentStrikes++;
                var strikeBox = document.getElementById('o-strike-' + opponentStrikes);
                if (strikeBox) {
                    strikeBox.classList.add('struck');
                    strikeBox.textContent = '✗';
                }

                if (opponentStrikes >= 3) {
                    endBattle('Opponent struck out!');
                    return;
                }
            } else {
                opponentScore++;
                var oScoreEl = document.getElementById('opponent-score');
                if (oScoreEl) oScoreEl.textContent = opponentScore;
                updateRaceBar();
            }

            scheduleNextAction();
        }, delay);
    }

    scheduleNextAction();
}

/**
 * Updates the central split race bar to reflect the relative lead.
 */
function updateRaceBar() {
    var playerBar = document.getElementById('race-bar-player');
    var opponentBar = document.getElementById('race-bar-opponent');
    if (!playerBar || !opponentBar) return;

    var total = playerScore + opponentScore;
    if (total === 0) {
        playerBar.style.width = '50%';
        opponentBar.style.width = '50%';
        return;
    }

    var playerPercent = Math.round((playerScore / total) * 100);
    // Maintain a visible minimum width of 10%
    playerPercent = Math.max(10, Math.min(90, playerPercent));
    var opponentPercent = 100 - playerPercent;

    playerBar.style.width = playerPercent + '%';
    opponentBar.style.width = opponentPercent + '%';
}

/**
 * Loads the next puzzle from the low-latency session cache.
 */
function loadNextBattlePuzzle() {
    if (isBattleOver) return;

    var img = document.getElementById('battle-puzzle-img');
    var input = document.getElementById('battle-input');

    getPuzzle()
        .then(function (url) {
            img.src = url;
            if (input) {
                input.value = '';
                input.disabled = false;
                input.focus();
            }
            isCheckingGuess = false;
        })
        .catch(function (err) {
            console.error('Puzzle load error:', err);
            isCheckingGuess = false;
        });
}

/**
 * Evaluates the player's submitted guess, updates strikes or score,
 * and triggers match termination if 3 strikes are reached.
 */
function handleBattleSubmit(event) {
    event.preventDefault();

    if (isBattleOver || isCheckingGuess) return;

    var input = document.getElementById('battle-input');
    var feedback = document.getElementById('battle-feedback');
    var guessText = input.value.trim();

    if (!/^[0-9]$/.test(guessText)) {
        return;
    }

    isCheckingGuess = true;
    input.disabled = true;

    submitAnswer(Number(guessText))
        .then(function (correct) {
            if (correct) {
                playerScore++;
                var pScoreEl = document.getElementById('player-score');
                if (pScoreEl) pScoreEl.textContent = playerScore;

                if (feedback) {
                    feedback.textContent = '✓ Correct!';
                    feedback.className = 'battle-feedback correct';
                }

                updateRaceBar();
                loadNextBattlePuzzle();
            } else {
                playerStrikes++;
                var strikeBox = document.getElementById('p-strike-' + playerStrikes);
                if (strikeBox) {
                    strikeBox.classList.add('struck');
                    strikeBox.textContent = '✗';
                }

                if (feedback) {
                    feedback.textContent = '✗ Strike ' + playerStrikes + ' / 3!';
                    feedback.className = 'battle-feedback wrong';
                }

                if (playerStrikes >= 3) {
                    endBattle('You struck out (3 strikes)!');
                } else {
                    loadNextBattlePuzzle();
                }
            }
        })
        .catch(function (err) {
            console.error('Error submitting guess:', err);
            isCheckingGuess = false;
            input.disabled = false;
        });
}

/**
 * Concludes the duel, posts results to /api/battle.php, and displays the summary modal.
 */
function endBattle(reason) {
    if (isBattleOver) return;
    isBattleOver = true;

    if (matchTimer) clearInterval(matchTimer);
    if (opponentTimer) clearTimeout(opponentTimer);

    var input = document.getElementById('battle-input');
    var submitBtn = document.getElementById('battle-submit-btn');
    if (input) input.disabled = true;
    if (submitBtn) submitBtn.disabled = true;

    fetch('/api/battle.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'finish',
            player_score: playerScore,
            opponent_score: opponentScore,
            player_strikes: playerStrikes,
            opponent_strikes: opponentStrikes
        })
    })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            var modal = document.getElementById('battle-modal');
            var titleEl = document.getElementById('modal-title');
            var subtitleEl = document.getElementById('modal-subtitle');
            var pScoreEl = document.getElementById('modal-player-score');
            var oScoreEl = document.getElementById('modal-opponent-score');
            var ratingBadgeEl = document.getElementById('modal-rating-badge');

            if (pScoreEl) pScoreEl.textContent = playerScore;
            if (oScoreEl) oScoreEl.textContent = opponentScore;
            if (subtitleEl) subtitleEl.textContent = reason;

            if (titleEl) {
                if (result.outcome === 'win') {
                    titleEl.textContent = '🏆 VICTORY!';
                    titleEl.className = 'modal-title win';
                } else if (result.outcome === 'loss') {
                    titleEl.textContent = '💀 DEFEAT';
                    titleEl.className = 'modal-title loss';
                } else {
                    titleEl.textContent = '🤝 DRAW';
                    titleEl.className = 'modal-title draw';
                }
            }

            if (ratingBadgeEl) {
                var deltaStr = result.rating_delta >= 0 ? '+' + result.rating_delta : '' + result.rating_delta;
                ratingBadgeEl.textContent = 'Battle Rating: ' + result.current_rating + ' (' + deltaStr + ')';
            }

            if (modal) modal.hidden = false;
        })
        .catch(function (err) {
            console.error('Error finishing battle:', err);
        });
}

/**
 * Attaches DOM listeners on load.
 */
function setupBattleEvents() {
    var form = document.getElementById('battle-form');
    if (form) {
        form.addEventListener('submit', handleBattleSubmit);
    }

    initBattle();
}

document.addEventListener('DOMContentLoaded', setupBattleEvents);
