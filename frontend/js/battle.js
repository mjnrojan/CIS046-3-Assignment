/**
 * =====================================================================
 * BANANA PUZZLE ARENA – 1v1 PUZZLE BATTLE CONTROLLER
 * =====================================================================
 *
 * Implements real-time multiplayer duel coordination:
 * - 60-second Elo-based matchmaking queue (|rating difference| <= 200).
 * - Instant pairing between two browsers (e.g. pilot_player & rival_player).
 * - Room state polling (every 1.5s) to synchronize opponent score & strikes.
 * - Challenger Bot fallback if solo or search expires.
 * - Synchronized 3-minute countdown clock and live split HUD race bar.
 * - Match resolution and Elo rating updates (+25 on win, -15 on loss).
 */

var timeRemaining = 180; // 3 minutes
var matchTimer = null;
var isBattleOver = false;
var isCheckingGuess = false;

var playerScore = 0;
var opponentScore = 0;
var playerStrikes = 0;
var opponentStrikes = 0;

var userRating = 500;
var opponentRating = 520;
var currentUsername = 'Player';
var opponentUsername = 'Opponent';

// Multiplayer state variables
var activeRoomId = null;
var isMultiplayer = false;
var queuePollingInterval = null;
var roomSyncInterval = null;
var queueElapsedSeconds = 0;
var maxQueueSeconds = 60; // 1-minute search window

// Opponent AI simulation timer (used for solo bot matches only)
var opponentBotTimer = null;

/**
 * Initializes the matchmaking queue upon entering battle.html.
 */
function startMatchmaking() {
    // 1. First, fetch user profile to populate HUD and get current Elo
    fetch('/api/me.php', { credentials: 'same-origin' })
        .then(function (res) {
            if (res.status === 401) {
                window.location.href = 'login.html';
                return null;
            }
            return res.json();
        })
        .then(function (user) {
            if (!user) return;

            currentUsername = user.username;
            userRating = user.battle_rating || 500;

            var pNameEl = document.getElementById('player-username');
            var pRatingEl = document.getElementById('player-rating-badge');
            if (pNameEl) pNameEl.textContent = currentUsername;
            if (pRatingEl) pRatingEl.textContent = userRating;

            var qSubtitle = document.getElementById('queue-subtitle');
            if (qSubtitle) {
                qSubtitle.textContent = 'Searching for players near ~' + userRating + ' Elo (±200 rating range).';
            }

            // 2. Register into the queue
            joinQueue();
        });
}

/**
 * Sends queue_join request to /api/battle.php and begins periodic polling.
 */
function joinQueue() {
    fetch('/api/battle.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'queue_join' })
    })
        .then(function (res) { return res.json(); })
        .then(function () {
            queueElapsedSeconds = 0;
            updateQueueTimerDisplay();

            // Poll every 1.5 seconds for an opponent
            queuePollingInterval = setInterval(pollMatchmakingQueue, 1500);
        });
}

/**
 * Polls /api/battle.php to check for matched rooms and refresh heartbeat.
 */
function pollMatchmakingQueue() {
    queueElapsedSeconds++;
    updateQueueTimerDisplay();

    // If 60 seconds have elapsed with no match, present fallback options
    if (queueElapsedSeconds >= maxQueueSeconds) {
        clearInterval(queuePollingInterval);
        var qTitle = document.getElementById('queue-title');
        var qStatus = document.getElementById('queue-status');
        if (qTitle) qTitle.textContent = 'No Opponent Found';
        if (qStatus) {
            qStatus.textContent = 'No player within 200 Elo found. Battle Challenger Bot or search again?';
        }
        return;
    }

    fetch('/api/battle.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'queue_poll' })
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.status === 'matched') {
                // Match Found!
                clearInterval(queuePollingInterval);
                activeRoomId = data.room_id;
                isMultiplayer = true;

                opponentUsername = data.opponent_name;
                opponentRating = data.opponent_rating;

                launchMatch();
            }
        })
        .catch(function (err) {
            console.error('Error polling matchmaking queue:', err);
        });
}

/**
 * Updates the 00:00 / 01:00 queue timer on the searching modal.
 */
function updateQueueTimerDisplay() {
    var timerEl = document.getElementById('queue-timer');
    if (!timerEl) return;

    var minutes = Math.floor(queueElapsedSeconds / 60);
    var seconds = queueElapsedSeconds % 60;
    var str = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    timerEl.textContent = str + ' / 01:00';
}

/**
 * Launches a solo match against Challenger Bot.
 */
function startSoloBotMatch() {
    if (queuePollingInterval) clearInterval(queuePollingInterval);

    fetch('/api/battle.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start' })
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            isMultiplayer = false;
            activeRoomId = null;

            opponentUsername = data.opponent_name;
            opponentRating = data.opponent_rating;

            launchMatch();
            startOpponentBotAI();
        });
}

/**
 * Launches the battle arena once an opponent (human or bot) is paired.
 */
function launchMatch() {
    // Hide matchmaking modal
    var modal = document.getElementById('matchmaking-modal');
    if (modal) modal.hidden = true;

    // Update opponent HUD
    var oNameEl = document.getElementById('opponent-name');
    var oRatingEl = document.getElementById('opponent-rating-badge');
    if (oNameEl) oNameEl.textContent = opponentUsername;
    if (oRatingEl) oRatingEl.textContent = opponentRating;

    timeRemaining = 180;
    updateTimerDisplay();
    startBattleTimer();

    if (isMultiplayer) {
        // Start bidirectional synchronization loop every 1.5s
        roomSyncInterval = setInterval(syncMultiplayerRoom, 1500);
    }

    loadNextBattlePuzzle();
}

/**
 * Synchronizes room state with the backend for live 2-browser duels.
 */
function syncMultiplayerRoom() {
    if (isBattleOver || !activeRoomId) return;

    fetch('/api/battle.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'room_sync',
            room_id: activeRoomId,
            score: playerScore,
            strikes: playerStrikes
        })
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data) return;

            opponentScore = data.opponent_score;
            opponentStrikes = data.opponent_strikes;

            // Update opponent score in HUD
            var oScoreEl = document.getElementById('opponent-score');
            if (oScoreEl) oScoreEl.textContent = opponentScore;

            // Update opponent strike boxes
            for (var i = 1; i <= 3; i++) {
                var sBox = document.getElementById('o-strike-' + i);
                if (sBox) {
                    if (i <= opponentStrikes) {
                        sBox.classList.add('struck');
                        sBox.textContent = '✗';
                    } else {
                        sBox.classList.remove('struck');
                        sBox.textContent = '' + i;
                    }
                }
            }

            updateRaceBar();

            // If opponent struck out, end match with player victory
            if (opponentStrikes >= 3 && playerStrikes < 3) {
                endBattle('Opponent struck out (3 strikes)!');
            }
        })
        .catch(function (err) {
            console.error('Room sync error:', err);
        });
}

/**
 * Updates the 3-minute match countdown display in MM:SS format.
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
 * Runs the match countdown clock.
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
 * Simulates Challenger Bot opponent when playing solo.
 */
function startOpponentBotAI() {
    function scheduleNextAction() {
        if (isBattleOver || isMultiplayer) return;

        var delay = Math.floor(Math.random() * 5000) + 7000;

        opponentBotTimer = setTimeout(function () {
            if (isBattleOver || isMultiplayer) return;

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
 * Adjusts the central race bar width percentage.
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
    playerPercent = Math.max(10, Math.min(90, playerPercent));
    var opponentPercent = 100 - playerPercent;

    playerBar.style.width = playerPercent + '%';
    opponentBar.style.width = opponentPercent + '%';
}

/**
 * Loads a fresh puzzle from session memory cache (< 10ms).
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
 * Handles player guess submission and synchronizes state.
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

                // Immediately sync live score so opponent sees it right away
                if (isMultiplayer) syncMultiplayerRoom();

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

                // Immediately sync strikes
                if (isMultiplayer) syncMultiplayerRoom();

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
 * Concludes the duel, calls /api/battle.php to record result and update Elo,
 * and presents the summary modal.
 */
function endBattle(reason) {
    if (isBattleOver) return;
    isBattleOver = true;

    if (matchTimer) clearInterval(matchTimer);
    if (queuePollingInterval) clearInterval(queuePollingInterval);
    if (roomSyncInterval) clearInterval(roomSyncInterval);
    if (opponentBotTimer) clearTimeout(opponentBotTimer);

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
            opponent_strikes: opponentStrikes,
            room_id: activeRoomId
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
 * Cancels matchmaking queue and returns to Arena.
 */
function cancelMatchmaking() {
    if (queuePollingInterval) clearInterval(queuePollingInterval);

    fetch('/api/battle.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'queue_leave' })
    })
        .then(function () {
            window.location.href = 'arena.html';
        })
        .catch(function () {
            window.location.href = 'arena.html';
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

    var btnBot = document.getElementById('btn-force-bot');
    if (btnBot) {
        btnBot.addEventListener('click', startSoloBotMatch);
    }

    var btnCancel = document.getElementById('btn-cancel-queue');
    if (btnCancel) {
        btnCancel.addEventListener('click', cancelMatchmaking);
    }

    startMatchmaking();
}

document.addEventListener('DOMContentLoaded', setupBattleEvents);
