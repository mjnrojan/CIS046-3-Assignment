/**
 * toast.js — Shared encouragement toast engine.
 *
 * Provides showEncouragementToast(), fetchAndShowToast(), and showLoadingTip()
 * for Classic, Rush, and Battle modes. Loaded before mode scripts so functions
 * are globally available.
 */

/**
 * Renders the toast banner with an affirmation and optional number fact.
 * Positioned cleanly under the top bar with auto-dismiss after 4.5 seconds.
 */
function showEncouragementToast(message, fact, label) {
    var toast   = document.getElementById('encouragement-toast');
    var labelEl = document.getElementById('toast-label');
    var msgEl   = document.getElementById('toast-message');
    var factEl  = document.getElementById('toast-fact');

    if (!toast) return;

    if (labelEl) labelEl.textContent = label || '💬 Keep it up!';
    if (msgEl)   msgEl.textContent   = message || 'You are doing great!';

    if (fact && factEl) {
        factEl.textContent = '🔢 ' + fact;
        factEl.hidden = false;
    } else if (factEl) {
        factEl.hidden = true;
    }

    toast.hidden = false;

    // Reset dismiss timer so rapid solves or strikes do not cut the toast short
    if (window._toastTimer) {
        clearTimeout(window._toastTimer);
    }
    window._toastTimer = setTimeout(function () {
        toast.hidden = true;
    }, 4500);
}

/**
 * Fetches an affirmation and math fact from /api/encouragement.php.
 * If digit is provided (0-9), retrieves that digit's fact; otherwise
 * the backend chooses a random digit for loading screens.
 */
function fetchAndShowToast(digit, label) {
    var url = '/api/encouragement.php';
    if (typeof digit === 'number' && digit >= 0 && digit <= 9) {
        url += '?answer=' + digit;
    }

    fetch(url, { credentials: 'same-origin' })
        .then(function (res) {
            if (!res.ok) return null;
            return res.json();
        })
        .then(function (enc) {
            if (!enc) return;
            showEncouragementToast(enc.affirmation, enc.fact, label);
        })
        .catch(function () {
            // Graceful degradation: toast blips never disrupt game flow
        });
}

/**
 * Shows a loading tip with a random math fact while a puzzle
 * or battle match is being prepared.
 */
function showLoadingTip(customLabel) {
    fetchAndShowToast(null, customLabel || '🍌 Math Fact');
}
