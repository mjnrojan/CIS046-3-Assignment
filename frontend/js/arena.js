/**
 * Banana Puzzle Arena – Hub controller.
 *
 * Fetches authenticated user identity and stats from /api/me.php,
 * updates the Chess.com-style rating badges, and handles mode routing
 * for Classic Ladder, Puzzle Rush, and Puzzle Battle.
 */

/**
 * Loads the current player's profile data from /api/me.php.
 * If the session is missing or expired, redirects to login.html.
 */
function loadUserProfile() {
    fetch('/api/me.php', { credentials: 'same-origin' })
        .then(function (response) {
            if (response.status === 401) {
                // Not logged in -> send to login screen
                window.location.href = 'login.html';
                return null;
            }
            if (!response.ok) {
                throw new Error('Failed to load profile');
            }
            return response.json();
        })
        .then(function (data) {
            if (!data) {
                return;
            }

            // Populate profile header banner
            var usernameEl = document.getElementById('profile-username');
            var statRushEl = document.getElementById('stat-rush');
            var statBattleEl = document.getElementById('stat-battle');
            var statLadderEl = document.getElementById('stat-ladder');

            if (usernameEl) usernameEl.textContent = data.username;
            if (statRushEl) statRushEl.textContent = data.rush_high_score;
            if (statBattleEl) statBattleEl.textContent = data.battle_rating;
            if (statLadderEl) statLadderEl.textContent = Number(data.ladder_points).toLocaleString();

            // Populate mode card details
            var cardLadderNode = document.getElementById('card-ladder-node');
            var cardRushScore = document.getElementById('card-rush-score');
            var cardBattleRating = document.getElementById('card-battle-rating');

            if (cardLadderNode) cardLadderNode.textContent = 'Node #' + data.ladder_node;
            if (cardRushScore) cardRushScore.textContent = data.rush_high_score + ' Solves';
            if (cardBattleRating) cardBattleRating.textContent = data.battle_rating + ' Elo';
        })
        .catch(function (error) {
            console.error('Profile fetch error:', error);
        });
}

/**
 * Handles logout by POSTing to /api/logout.php and redirecting.
 */
function handleLogout() {
    fetch('/api/logout.php', {
        method: 'POST',
        credentials: 'same-origin'
    }).then(function () {
        window.location.href = 'login.html';
    });
}

/**
 * Attaches modal and navigation listeners.
 */
function setupArenaEvents() {
    // Logout button
    var logoutBtn = document.getElementById('btn-logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }

    // Puzzle Rush duration modal toggle
    var openRushBtn = document.getElementById('btn-open-rush-modal');
    var closeRushBtn = document.getElementById('btn-close-rush');
    var rushModal = document.getElementById('rush-modal');

    if (openRushBtn && rushModal) {
        openRushBtn.addEventListener('click', function () {
            rushModal.hidden = false;
        });
    }

    if (closeRushBtn && rushModal) {
        closeRushBtn.addEventListener('click', function () {
            rushModal.hidden = true;
        });
    }

    // Puzzle Rush mode buttons (1m, 5m, 10m, survival)
    var rushOptions = document.querySelectorAll('.btn-option');
    rushOptions.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var duration = btn.getAttribute('data-duration');
            if (duration === '0') {
                window.location.href = 'rush.html?mode=survival';
            } else {
                window.location.href = 'rush.html?duration=' + duration;
            }
        });
    });

    // Leaderboard tab navigation
    var tabLeaderboard = document.getElementById('tab-leaderboard');
    if (tabLeaderboard) {
        tabLeaderboard.addEventListener('click', function () {
            window.location.href = 'leaderboard.html';
        });
    }
}

// Initialise when the DOM loads
window.addEventListener('load', function () {
    loadUserProfile();
    setupArenaEvents();
});
