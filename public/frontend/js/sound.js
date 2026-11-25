/**
 * =====================================================================
 * BANANA PUZZLE ARENA – ZERO-LAG SOUND ENGINE  (sound.js)
 * =====================================================================
 *
 * Two-layer latency strategy:
 *
 * Layer 1 – Server (PHP Session Cache):
 *   /api/sounds_preload.php fetches all Freesound CDN URLs in one go
 *   and stores them in $_SESSION. Every page load after the first is
 *   instant — no Freesound API call needed.
 *
 * Layer 2 – Browser (Audio Buffer Pool):
 *   On page load we download every MP3 from the CDN and hold them in
 *   browser memory as pre-decoded Audio objects. playSound() just calls
 *   .play() on an already-buffered object — sub-millisecond response.
 *
 * Usage from any page (after this script is included):
 *   playSound('click');    // button tap
 *   playSound('solve');    // correct answer
 *   playSound('strike');   // wrong answer / strike
 *   playSound('levelup');  // ladder node advance
 *   playSound('gameover'); // game over state
 *   toggleMute();          // flip mute state
 */

// Pool of pre-buffered Audio objects keyed by category
// e.g. audioPool['click'] = [Audio, Audio]  (clones for rapid re-fire)
var audioPool = {};

// Raw URL cache received from /api/sounds_preload.php
var soundUrls = {};

// Mute state — persisted across page reloads via localStorage
var isMuted = (localStorage.getItem('bpa_muted') === 'true');

// Whether the browser's autoplay policy has been unlocked by user gesture
var audioUnlocked = false;

/**
 * Fetches all sound URLs in a single batch request to the PHP proxy,
 * then pre-downloads each MP3 and buffers it into audioPool.
 * Called once on DOMContentLoaded.
 */
function preloadAllSounds() {
    fetch('/api/sounds_preload.php', { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (urls) {
            if (!urls) return;
            soundUrls = urls;

            // Pre-buffer every sound immediately after fetching URLs
            Object.keys(urls).forEach(function (category) {
                bufferSound(category, urls[category]);
            });
        })
        .catch(function (err) {
            // Sound failure must never break gameplay — silently continue
            console.warn('[SoundEngine] Preload failed:', err);
        });
}

/**
 * Creates two Audio objects for a category and forces the browser to
 * download and decode the MP3 by calling .load().
 * Two copies allow rapid re-fire (e.g. fast button clicks) without
 * the second click waiting for the first to finish.
 */
function bufferSound(category, url) {
    audioPool[category] = [];
    for (var i = 0; i < 2; i++) {
        var audio = new Audio(url);
        audio.volume = 0.6;
        audio.preload = 'auto'; // tell the browser to download now
        audio.load();           // trigger download immediately
        audioPool[category].push(audio);
    }
}

/**
 * Plays the buffered audio for the given category.
 * Rotates between the two clones so rapid calls never block each other.
 * Does nothing if muted or if the pool is not yet ready.
 */
var poolIndex = {}; // tracks which clone to use next per category

function playSound(category) {
    if (isMuted) return;
    if (!audioPool[category] || audioPool[category].length === 0) return;

    // Pick the next clone in round-robin fashion
    if (poolIndex[category] === undefined) poolIndex[category] = 0;
    var idx = poolIndex[category] % audioPool[category].length;
    poolIndex[category]++;

    var audio = audioPool[category][idx];

    // Rewind to start in case it is mid-playback from a previous call
    audio.currentTime = 0;
    audio.play().catch(function () {
        // Browser blocks autoplay before first user gesture — unlock on next interaction
        if (!audioUnlocked) {
            pendingSound = category;
        }
    });
}

// If a sound was blocked by autoplay policy, store it and play on first touch/click
var pendingSound = null;

/**
 * Unlocks the browser's autoplay policy on the first user gesture.
 * Plays any sound that was blocked while waiting for the gesture.
 */
function unlockAudio() {
    if (audioUnlocked) return;
    audioUnlocked = true;

    // Play and immediately pause a silent buffer to satisfy the autoplay guard
    var silencer = new Audio();
    silencer.play().catch(function () {});

    if (pendingSound) {
        playSound(pendingSound);
        pendingSound = null;
    }
}

/**
 * Toggles the global mute state.
 * Persists the choice in localStorage so it survives page reloads.
 */
function toggleMute() {
    isMuted = !isMuted;
    localStorage.setItem('bpa_muted', isMuted ? 'true' : 'false');
    updateMuteButtons();
}

/**
 * Syncs all .btn-mute-icon elements on the page with the current mute state.
 */
function updateMuteButtons() {
    document.querySelectorAll('.btn-mute-icon').forEach(function (el) {
        el.textContent = isMuted ? '🔇' : '🔊';
    });
}

/**
 * Wires up mute toggle buttons and autoplay unlock listeners,
 * then kicks off the background preload.
 */
function initSoundEngine() {
    updateMuteButtons();

    // Wire mute toggle buttons
    document.querySelectorAll('.btn-toggle-mute').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleMute();
        });
    });

    // Unlock autoplay on first user gesture anywhere on the page
    document.addEventListener('click', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    // Kick off background preload — does not block page render
    preloadAllSounds();
}

document.addEventListener('DOMContentLoaded', initSoundEngine);
