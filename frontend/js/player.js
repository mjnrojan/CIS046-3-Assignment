/**
 * Creates a player object at the given start position.
 *
 * The player is a simple rectangle with position, velocity and size.
 * Movement uses velocity so gravity and friction feel smooth.
 * Collision is checked by the game loop, not by the player itself,
 * because the player should not know about the level layout.
 */
function createPlayer(startX, startY) {
    return {
        x: startX,
        y: startY,
        width: 28,
        height: 40,
        vx: 0,
        vy: 0,
        speed: 3,
        jumpForce: -8,
        onGround: false
    };
}

/**
 * Updates the player position based on which keys are held down.
 *
 * Horizontal movement sets velocity directly rather than using
 * acceleration. This gives tight, responsive controls like classic
 * 2D platformers. Gravity is applied every frame.
 */
function updatePlayer(player, keys, gravity) {
    // Horizontal movement: left and right.
    if (keys['ArrowLeft'] || keys['a']) {
        player.vx = -player.speed;
    } else if (keys['ArrowRight'] || keys['d']) {
        player.vx = player.speed;
    } else {
        player.vx = 0;
    }

    // Jump: only when standing on a solid surface.
    if ((keys['ArrowUp'] || keys['w'] || keys[' ']) && player.onGround) {
        player.vy = player.jumpForce;
        player.onGround = false;
    }

    // Gravity pulls the player down every frame.
    player.vy = player.vy + gravity;

    // Apply velocity to position.
    player.x = player.x + player.vx;
    player.y = player.y + player.vy;
}

/**
 * Draws the player as a coloured rectangle on the canvas.
 *
 * A simple rectangle is enough for collision detection and is easy
 * to explain. Sprites could replace this later without changing
 * the movement logic.
 */
function drawPlayer(ctx, player, cameraX) {
    ctx.fillStyle = '#e74c3c';
    ctx.fillRect(
        player.x - cameraX,
        player.y,
        player.width,
        player.height
    );
}
