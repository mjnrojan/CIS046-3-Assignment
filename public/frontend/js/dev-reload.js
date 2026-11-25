/**
 * Client-side development live reloader.
 *
 * Periodically polls /api/dev_watch.php for filesystem changes.
 * When an edit to HTML, JS, or CSS is saved on disk, this script automatically
 * reloads the page so the developer immediately sees updates without manual Ctrl+F5.
 */

(function () {
    // Only activate in local development environments.
    var isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    if (!isLocalhost) {
        return;
    }

    var initialTimestamp = null;
    var pollIntervalMs = 1200;

    /**
     * Checks if any frontend file has changed on the server.
     * Uses cache: 'no-cache' to avoid browser HTTP caching.
     */
    function checkForFileChanges() {
        fetch('/api/dev_watch.php', { cache: 'no-cache' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Watch endpoint unavailable');
                }
                return response.json();
            })
            .then(function (data) {
                if (typeof data.timestamp !== 'number') {
                    return;
                }

                // First check establishes baseline timestamp.
                if (initialTimestamp === null) {
                    initialTimestamp = data.timestamp;
                    return;
                }

                // If timestamp increased, a file was modified.
                if (data.timestamp > initialTimestamp) {
                    console.log('[LiveReload] Change detected. Reloading page...');
                    window.location.reload();
                }
            })
            .catch(function (error) {
                // Silently ignore temporary network glitches while editing.
            });
    }

    // Start polling after page load.
    window.addEventListener('load', function () {
        setInterval(checkForFileChanges, pollIntervalMs);
    });
})();
