/**
 * Classic Ladder client controller.
 *
 * Coordinates stepping stone path rendering, puzzle loading,
 * and guess submission with server-side 50-point chip-away scoring.
 */

var currentLadderNode = 1;
var totalLadderPoints = 0;
var isSubmitting = false;

/**
 * Renders the visual stepping stone trail.
 * Displays completed nodes, the active current node, and upcoming nodes.
 */
function renderSteppingStones(activeNode) {
    var container = document.getElementById('stones-container');
    if (!container) return;

    container.innerHTML = '';

    // Show a window of nodes: up to 6 nodes ahead
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

            // Update top bar
            var pointsEl = document.getElementById('header-ladder-points');
            var nodeIndEl = document.getElementById('header-node-indicator');
            var activeTitleEl = document.getElementById('active-node-title');
            var rewardBadge = document.getElementById('potential-reward-badge');

            if (pointsEl) pointsEl.textContent = totalLadderPoints.toLocaleString();
            if (nodeIndEl) nodeIndEl.textContent = 'Node #' + currentLadderNode;
            if (activeTitleEl) activeTitleEl.textContent = 'Active: Node #' + currentLadderNode;
            if (rewardBadge) rewardBadge.textContent = '⭐ Potential: +' + data.potential_points + ' pts';

            renderSteppingStones(currentLadderNode);
            loadNextLadderPuzzle();
        })
        .catch(function (error) {
            console.error('Ladder state error:', error);
        });
}

/**
 * Loads a fresh puzzle into the view via /api/puzzle.php (served from cache).
 */
function loadNextLadderPuzzle() {
    var img = document.getElementById('ladder-puzzle-img');
    var statusMsg = document.getElementById('ladder-status-msg');

    if (statusMsg) {
        statusMsg.textContent = 'Loading node challenge...';
        statusMsg.className = 'status-msg';
    }

    getPuzzle()
        .then(function (url) {
            img.src = url;
            if (statusMsg) statusMsg.textContent = '';
            var input = document.getElementById('ladder-guess-input');
            if (input) {
                input.value = '';
                input.focus();
            }
        })
        .catch(function (error) {
            if (statusMsg) {
                statusMsg.textContent = error.message;
                statusMsg.className = 'status-msg error';
            }
        });
}

/**
 * Attaches form submission and guess checking logic.
 */
function setupLadderEvents() {
    var form = document.getElementById('ladder-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (isSubmitting) return;

        var input = document.getElementById('ladder-guess-input');
        var statusMsg = document.getElementById('ladder-status-msg');
        var rewardBadge = document.getElementById('potential-reward-badge');
        var submitBtn = document.getElementById('btn-ladder-submit');
        var guessText = input.value.trim();

        if (!/^[0-9]$/.test(guessText)) {
            statusMsg.textContent = 'Please enter a digit from 0 to 9.';
            statusMsg.className = 'status-msg error';
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;

        fetch('/api/ladder.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ guess: Number(guessText) })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.correct) {
                    // Wrong answer: chip away potential points, retry same puzzle!
                    statusMsg.textContent = result.message + ' (Mistake #' + result.attempts + ')';
                    statusMsg.className = 'status-msg error';
                    rewardBadge.textContent = '⭐ Potential: +' + result.potential_points + ' pts';
                    input.select();
                } else {
                    // Correct answer: award points and advance node
                    statusMsg.textContent = '🎉 ' + result.message + ' +' + result.points_earned + ' pts!';
                    statusMsg.className = 'status-msg success';

                    totalLadderPoints = result.total_ladder_points;
                    currentLadderNode = result.next_node;

                    // Update UI stats
                    var pointsEl = document.getElementById('header-ladder-points');
                    var nodeIndEl = document.getElementById('header-node-indicator');
                    var activeTitleEl = document.getElementById('active-node-title');

                    if (pointsEl) pointsEl.textContent = totalLadderPoints.toLocaleString();
                    if (nodeIndEl) nodeIndEl.textContent = 'Node #' + currentLadderNode;
                    if (activeTitleEl) activeTitleEl.textContent = 'Active: Node #' + currentLadderNode;
                    rewardBadge.textContent = '⭐ Potential: +50 pts';

                    renderSteppingStones(currentLadderNode);

                    // Load next node puzzle after a short celebration pause
                    setTimeout(function () {
                        loadNextLadderPuzzle();
                    }, 1000);
                }
            })
            .catch(function (err) {
                statusMsg.textContent = err.message;
                statusMsg.className = 'status-msg error';
            })
            .finally(function () {
                isSubmitting = false;
                submitBtn.disabled = false;
            });
    });
}

// Start ladder mode on window load
window.addEventListener('load', function () {
    loadLadderState();
    setupLadderEvents();
});
