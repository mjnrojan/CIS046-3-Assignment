/**
 * Classic Ladder client controller.
 *
 * Coordinates puzzle loading and guess submission with server-side
 * 50-point chip-away scoring. Relies on toast.js for encouragement
 * toasts and sound.js for audio feedback.
 */

var currentLadderNode = 1;
var totalLadderPoints = 0;
var isSubmitting = false;

/**
 * Renders the visual stepping stone trail.
 * Falls back gracefully if #stones-container does not exist in the DOM.
 */
function renderSteppingStones(activeNode) {
    var container = document.getElementById('stones-container');
    if (!container) return;   // wide layout has no sidebar — safe no-op

    container.innerHTML = '';
    var maxNodeToShow = Math.max(10, activeNode + 5);

    for (var i = 1; i <= maxNodeToShow; i++) {
        var nodeEl = document.createElement('div');
        nodeEl.className = 'stone-node';

        if (i < activeNode) {
            nodeEl.classList.add('completed');
            nodeEl.innerHTML = 'Node #' + i + ' ✔';
        } else if (i === activeNode) {
            nodeEl.classList.add('active');
            nodeEl.innerHTML = '♟️ Node #' + i;
        } else {
            nodeEl.textContent = 'Node #' + i;
        }

        container.appendChild(nodeEl);
    }
}

/**
 * Loads the current ladder state from /api/ladder.php.
 */
function loadLadderState() {
    fetch('/api/ladder.php', { credentials: 'same-origin' })
        .then(function (response) {
            if (response.status === 401) {
                window.location.href = 'login.html';
                return null;
            }
            return response.json();
        })
        .then(function (data) {
            if (!data) return;

            currentLadderNode = data.ladder_node;
            totalLadderPoints = data.ladder_points;

            var pointsEl  = document.getElementById('header-ladder-points');
            var nodeIndEl = document.getElementById('header-node-indicator');

            if (pointsEl)  pointsEl.textContent  = totalLadderPoints.toLocaleString();
            if (nodeIndEl) nodeIndEl.textContent  = 'Node #' + currentLadderNode;

            renderSteppingStones(currentLadderNode);
        })
        .catch(function () {
            // Silent — header stats are non-critical
            renderSteppingStones(1);
        });
}

/**
 * Loads a fresh puzzle image via /api/puzzle.php.
 * While the image is fetching, shows a fun number fact in the toast
 * so the player has something interesting to read during the load.
 */
function loadNextLadderPuzzle() {
    var img       = document.getElementById('ladder-puzzle-img');
    var loader    = document.getElementById('ladder-puzzle-loader');
    var statusMsg = document.getElementById('ladder-status-msg');

    if (loader) loader.style.display = 'block';
    if (img)    img.style.display    = 'none';
    if (statusMsg) {
        statusMsg.textContent = '';
        statusMsg.className   = 'status-line';
    }

    // Show a fun number fact while the puzzle loads
    if (typeof showLoadingTip === 'function') { showLoadingTip(); }

    getPuzzle()
        .then(function (url) {
            img.onload = function () {
                if (loader) loader.style.display = 'none';
                img.style.display = 'block';
            };
            img.src = url;

            var input = document.getElementById('ladder-guess-input');
            if (input) { input.value = ''; input.focus(); }
        })
        .catch(function () {
            if (loader) loader.style.display = 'none';
            if (statusMsg) {
                statusMsg.textContent = 'Could not load puzzle. Please refresh the page.';
                statusMsg.className   = 'status-line error';
            }
        });
}

/**
 * Attaches form submit logic with sound + encouragement toast on
 * both correct AND wrong answers so the player always gets feedback.
 */
function setupLadderEvents() {
    var form = document.getElementById('ladder-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (isSubmitting) return;

        var input     = document.getElementById('ladder-guess-input');
        var statusMsg = document.getElementById('ladder-status-msg');
        var submitBtn = document.getElementById('btn-ladder-submit');
        var guessText = input.value.trim();

        if (!/^[0-9]$/.test(guessText)) {
            statusMsg.textContent = 'Please enter a single digit (0–9).';
            statusMsg.className   = 'status-line error';
            return;
        }

        // Click sound the moment the player submits
        if (typeof playSound === 'function') { playSound('click'); }

        isSubmitting = true;
        submitBtn.disabled = true;

        fetch('/api/ladder.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ guess: Number(guessText) })
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                if (!result.correct) {
                    // Wrong answer — buzz + encouraging toast to keep morale up
                    statusMsg.textContent = '✗ Wrong answer — try again!';
                    statusMsg.className   = 'status-line error';
                    input.select();

                    if (typeof playSound === 'function') { playSound('strike'); }

                    // Toast: encouragement even on wrong so the player doesn't feel bad
                    if (typeof fetchAndShowToast === 'function') {
                        fetchAndShowToast(Number(guessText), '💪 Keep trying!');
                    }

                } else {
                    // Correct answer — celebrate with points + jingle + toast
                    statusMsg.textContent = '🎉 +' + result.points_earned + ' pts!';
                    statusMsg.className   = 'status-line correct';

                    totalLadderPoints = result.total_ladder_points;
                    currentLadderNode = result.next_node;

                    var pointsEl  = document.getElementById('header-ladder-points');
                    var nodeIndEl = document.getElementById('header-node-indicator');
                    if (pointsEl)  pointsEl.textContent  = totalLadderPoints.toLocaleString();
                    if (nodeIndEl) nodeIndEl.textContent  = 'Node #' + currentLadderNode;

                    renderSteppingStones(currentLadderNode);

                    if (typeof playSound === 'function') { playSound('levelup'); }

                    // Toast: affirmation + number fact for the digit they just solved
                    if (typeof fetchAndShowToast === 'function') {
                        fetchAndShowToast(Number(guessText), '✓ Correct!');
                    }

                    // Load next puzzle after a short celebration pause
                    setTimeout(function () { loadNextLadderPuzzle(); }, 1200);
                }
            })
            .catch(function () {
                statusMsg.textContent = 'Could not submit answer. Check your connection.';
                statusMsg.className   = 'status-line error';
            })
            .finally(function () {
                isSubmitting = false;
                submitBtn.disabled = false;
            });
    });
}

// Boot ladder mode once the DOM is fully loaded
window.addEventListener('load', function () {
    renderSteppingStones(1);
    loadLadderState();
    loadNextLadderPuzzle();
    setupLadderEvents();
});
