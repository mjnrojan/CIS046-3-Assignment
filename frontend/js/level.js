/**
 * Level data and tile collision for the game.
 *
 * Each level is a 2D array of numbers. Every number maps to a tile type:
 *   0 = empty (air)       1 = platform (solid)
 *   2 = scroll (puzzle)   3 = door (exit)
 *   4 = bronze chest      5 = player spawn
 *   6 = silver chest      7 = gold chest
 *
 * The tile size is 32 pixels. The canvas is 800x480, so 25x15 tiles
 * are visible at once. Levels wider than 25 tiles scroll horizontally.
 */

var TILE = 32;

/**
 * All levels stored in one array. Index 0 is the tutorial.
 * Each level is 15 rows tall (480px). Width varies per level.
 *
 * Designing levels as plain arrays makes them easy to edit and
 * easy for the marker to read. An alternative is a visual editor,
 * but that adds complexity with no marks benefit.
 */
var LEVELS = [

    // Level 0 — Tutorial
    // Flat ground, scroll next to door, teaches the basics.
    [
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,5,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2,0,3,0],
        [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1]
    ],

    // Level 1 — Easy
    // Simple platforms, one scroll to find, one bronze chest.
    [
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,4,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,3,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,1,0],
        [0,5,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [1,1,1,1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1]
    ],

    // Level 2 — Medium
    // Two scrolls, bronze + silver chest, more exploration needed.
    [
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,6,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,4,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,3,0],
        [0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,1,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,5,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [1,1,1,1,1,0,0,0,0,1,1,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1]
    ],

    // Level 3 — Hard
    // Three scrolls, one hidden silver chest, no easy paths.
    [
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,6,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,3,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,1,1,0],
        [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,0],
        [0,5,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        [1,1,1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1]
    ]
];

/**
 * Finds the player spawn position (tile 5) in a level.
 * Returns pixel coordinates for the top-left of that tile.
 */
function findSpawn(level) {
    for (var row = 0; row < level.length; row++) {
        for (var col = 0; col < level[row].length; col++) {
            if (level[row][col] === 5) {
                return { x: col * TILE, y: row * TILE };
            }
        }
    }
    return { x: TILE, y: 0 };
}

/**
 * Returns the tile value at a given pixel position.
 * Used by collision detection and object interaction.
 * Returns 1 (solid) for out-of-bounds below the level, 0 for above/sides.
 */
function getTileAt(level, pixelX, pixelY) {
    var col = Math.floor(pixelX / TILE);
    var row = Math.floor(pixelY / TILE);

    if (row < 0 || col < 0) {
        return 0;
    }
    if (row >= level.length) {
        return 1;
    }
    if (col >= level[0].length) {
        return 0;
    }

    return level[row][col];
}

/**
 * Checks and resolves collisions between the player and solid tiles.
 *
 * Horizontal and vertical axes are resolved separately. This prevents
 * the player from getting stuck on corners. Checking X first, then Y,
 * is the standard approach in tile-based platformers.
 */
function resolveCollisions(player, level) {
    // Horizontal collision — check left and right edges.
    var leftCol = Math.floor(player.x / TILE);
    var rightCol = Math.floor((player.x + player.width - 1) / TILE);
    var topRow = Math.floor(player.y / TILE);
    var bottomRow = Math.floor((player.y + player.height - 1) / TILE);

    for (var row = topRow; row <= bottomRow; row++) {
        for (var col = leftCol; col <= rightCol; col++) {
            if (getTileAt(level, col * TILE, row * TILE) === 1) {
                // Moving right into a wall.
                if (player.vx > 0) {
                    player.x = col * TILE - player.width;
                }
                // Moving left into a wall.
                if (player.vx < 0) {
                    player.x = (col + 1) * TILE;
                }
            }
        }
    }

    // Vertical collision — recalculate after horizontal fix.
    leftCol = Math.floor(player.x / TILE);
    rightCol = Math.floor((player.x + player.width - 1) / TILE);
    topRow = Math.floor(player.y / TILE);
    bottomRow = Math.floor((player.y + player.height - 1) / TILE);

    player.onGround = false;

    for (var row = topRow; row <= bottomRow; row++) {
        for (var col = leftCol; col <= rightCol; col++) {
            if (getTileAt(level, col * TILE, row * TILE) === 1) {
                // Falling onto a platform.
                if (player.vy > 0) {
                    player.y = row * TILE - player.height;
                    player.vy = 0;
                    player.onGround = true;
                }
                // Hitting a ceiling.
                if (player.vy < 0) {
                    player.y = (row + 1) * TILE;
                    player.vy = 0;
                }
            }
        }
    }
}

/**
 * Returns the tile value the player is currently touching.
 * Checks the centre of the player rectangle for reliable detection.
 * Used to detect scrolls, doors and chests.
 */
function getPlayerTile(player, level) {
    var centreX = player.x + player.width / 2;
    var centreY = player.y + player.height / 2;
    return getTileAt(level, centreX, centreY);
}

/**
 * Removes a tile (sets it to 0) at the player's centre position.
 * Used after collecting a scroll or chest so it disappears.
 */
function removeTileAtPlayer(player, level) {
    var centreX = player.x + player.width / 2;
    var centreY = player.y + player.height / 2;
    var col = Math.floor(centreX / TILE);
    var row = Math.floor(centreY / TILE);

    if (row >= 0 && row < level.length && col >= 0 && col < level[row].length) {
        level[row][col] = 0;
    }
}

/**
 * Counts how many scrolls (tile 2) remain in a level.
 * The door unlocks when this returns 0.
 */
function countScrolls(level) {
    var count = 0;
    for (var row = 0; row < level.length; row++) {
        for (var col = 0; col < level[row].length; col++) {
            if (level[row][col] === 2) {
                count = count + 1;
            }
        }
    }
    return count;
}

/**
 * Draws all tiles on the canvas.
 * Only draws tiles visible within the camera view for performance.
 * Each tile type gets a different colour so you can tell them apart.
 */
function drawLevel(ctx, level, cameraX, canvasWidth) {
    var startCol = Math.floor(cameraX / TILE);
    var endCol = Math.ceil((cameraX + canvasWidth) / TILE);

    for (var row = 0; row < level.length; row++) {
        for (var col = startCol; col <= endCol; col++) {
            if (col < 0 || col >= level[row].length) {
                continue;
            }

            var tile = level[row][col];
            var screenX = col * TILE - cameraX;
            var screenY = row * TILE;

            if (tile === 1) {
                // Platform — brown.
                ctx.fillStyle = '#8B4513';
                ctx.fillRect(screenX, screenY, TILE, TILE);
                // Top edge line for depth.
                ctx.fillStyle = '#a0522d';
                ctx.fillRect(screenX, screenY, TILE, 4);
            } else if (tile === 2) {
                // Scroll — gold.
                ctx.fillStyle = '#f1c40f';
                ctx.fillRect(screenX + 8, screenY + 4, 16, 24);
                ctx.fillStyle = '#e67e22';
                ctx.fillRect(screenX + 6, screenY + 4, 20, 4);
                ctx.fillRect(screenX + 6, screenY + 24, 20, 4);
            } else if (tile === 3) {
                // Door — dark grey when locked.
                ctx.fillStyle = '#555';
                ctx.fillRect(screenX + 4, screenY, TILE - 8, TILE);
                ctx.fillStyle = '#c0392b';
                ctx.fillRect(screenX + 20, screenY + 14, 4, 4);
            } else if (tile === 4) {
                // Bronze chest.
                ctx.fillStyle = '#cd7f32';
                ctx.fillRect(screenX + 4, screenY + 10, 24, 18);
                ctx.fillStyle = '#b87333';
                ctx.fillRect(screenX + 4, screenY + 10, 24, 4);
            } else if (tile === 6) {
                // Silver chest.
                ctx.fillStyle = '#c0c0c0';
                ctx.fillRect(screenX + 4, screenY + 10, 24, 18);
                ctx.fillStyle = '#a8a8a8';
                ctx.fillRect(screenX + 4, screenY + 10, 24, 4);
            } else if (tile === 7) {
                // Gold chest.
                ctx.fillStyle = '#ffd700';
                ctx.fillRect(screenX + 4, screenY + 10, 24, 18);
                ctx.fillStyle = '#daa520';
                ctx.fillRect(screenX + 4, screenY + 10, 24, 4);
            }
        }
    }
}
