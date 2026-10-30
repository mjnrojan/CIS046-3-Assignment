/**
 * =====================================================================
 * BANANA PUZZLE ARENA – LEADERBOARD CONTROLLER
 * =====================================================================
 *
 * Coordinates multi-mode leaderboard fetching, tab transitions,
 * personal standing presentation, and medal decorations.
 *
 * Modes supported:
 * - 'rush': Top solves in single sprint
 * - 'battle': Competitive Elo rating
 * - 'ladder': Cumulative adventure path points
 */

var currentMode = 'rush';

/**
 * Formats rank number with podium medals for top 3 positions.
 */
function formatRank(rank) {
    if (rank === 1) return '<span class="medal-1">🥇 1</span>';
    if (rank === 2) return '<span class="medal-2">🥈 2</span>';
    if (rank === 3) return '<span class="medal-3">🥉 3</span>';
    return '<span style="color: #888888;">#' + rank + '</span>';
}

/**
 * Fetches rankings from /api/leaderboard.php?mode=... and updates DOM.
 */
function fetchLeaderboard(mode) {
    var tbody = document.getElementById('leaderboard-body');
    var metricHeader = document.getElementById('metric-header');
    var standingCard = document.getElementById('user-standing-card');

    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="3" class="empty-row">Loading rankings...</td></tr>';
    }

    fetch('/api/leaderboard.php?mode=' + encodeURIComponent(mode), { credentials: 'same-origin' })
        .then(function (res) {
            if (!res.ok) throw new Error('Failed to load leaderboard data');
            return res.json();
        })
        .then(function (data) {
            if (!data) return;

            // Update column header label
            if (metricHeader) {
                metricHeader.textContent = data.metric_label || 'Score';
            }

            // Populate personal standing card
            if (standingCard) {
                if (data.user_standing) {
                    standingCard.hidden = false;
                    var sRank = document.getElementById('standing-rank');
                    var sUser = document.getElementById('standing-username');
                    var sScore = document.getElementById('standing-score');
                    var sDesc = document.getElementById('standing-metric-desc');

                    if (sRank) sRank.textContent = '#' + data.user_standing.rank;
                    if (sUser) sUser.textContent = data.user_standing.username;
                    if (sScore) sScore.textContent = Number(data.user_standing.score).toLocaleString();
                    if (sDesc) sDesc.textContent = 'Your ' + data.metric_label;
                } else {
                    standingCard.hidden = true;
                }
            }

            // Render table rows
            if (!tbody) return;

            if (!data.rankings || data.rankings.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="empty-row">No records yet. Be the first to play!</td></tr>';
                return;
            }

            var html = '';
            for (var i = 0; i < data.rankings.length; i++) {
                var row = data.rankings[i];
                var rowClass = row.is_self ? ' class="is-self"' : '';
                var youBadge = row.is_self ? ' <span class="you-badge">YOU</span>' : '';
                var nodeBadge = (mode === 'ladder' && row.node) ? ' <small style="color: #888888;">(Node #' + row.node + ')</small>' : '';

                html += '<tr' + rowClass + '>';
                html += '<td class="rank-cell">' + formatRank(row.rank) + '</td>';
                html += '<td><div class="player-cell"><strong>' + row.username + '</strong>' + youBadge + nodeBadge + '</div></td>';
                html += '<td class="score-cell">' + Number(row.score).toLocaleString() + '</td>';
                html += '</tr>';
            }

            tbody.innerHTML = html;
        })
        .catch(function (err) {
            console.error('Leaderboard error:', err);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="3" class="empty-row" style="color: #e06c75;">Failed to load leaderboard. Please try again.</td></tr>';
            }
        });
}

/**
 * Switches the active mode tab and refreshes rankings.
 */
function switchMode(mode) {
    currentMode = mode;

    var tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(function (tab) {
        if (tab.getAttribute('data-mode') === mode) {
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
        } else {
            tab.classList.remove('active');
            tab.setAttribute('aria-selected', 'false');
        }
    });

    fetchLeaderboard(mode);
}

/**
 * Attaches tab click listeners and loads initial mode.
 */
function setupLeaderboardEvents() {
    var tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var selectedMode = tab.getAttribute('data-mode');
            if (selectedMode && selectedMode !== currentMode) {
                switchMode(selectedMode);
            }
        });
    });

    // Parse URL parameter if player came with ?mode=battle or ?mode=ladder
    var params = new URLSearchParams(window.location.search);
    var urlMode = params.get('mode');
    if (urlMode && ['rush', 'battle', 'ladder'].indexOf(urlMode) !== -1) {
        switchMode(urlMode);
    } else {
        fetchLeaderboard('rush');
    }
}

document.addEventListener('DOMContentLoaded', setupLeaderboardEvents);

