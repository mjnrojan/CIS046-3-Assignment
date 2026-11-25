# Banana Puzzle Arena 🍌
**CIS046-3 Software for Enterprise — Academic Coursework Submission**

A full-stack, enterprise-grade arithmetic puzzle arena inspired by Chess.com. Built with plain, defensible PHP and vanilla JavaScript (no external frameworks or black-box libraries), designed specifically for clear examination defense across the four core module themes.

---

## Table of Contents
1. [Key Features](#key-features)
2. [Quick Setup & Local Execution](#quick-setup--local-execution)
3. [Pre-Seeded Demonstration Accounts](#pre-seeded-demonstration-accounts)
4. [Coursework Themes & Technical Defense](#coursework-themes--technical-defense)
   - [Theme 1: Virtual Identity](#theme-1-virtual-identity)
   - [Theme 2: Interoperability](#theme-2-interoperability)
   - [Theme 3: Event Handling](#theme-3-event-handling)
   - [Theme 4: Version Control](#theme-4-version-control)
5. [Automated Test Harness](#automated-test-harness)
6. [Project Structure](#project-structure)

---

## Key Features

- 🪜 **Classic Ladder Mode (`classic.html`)**: An adventure trail of math riddles. Players earn up to 50 points on their first attempt; wrong guesses chip away 15 points per attempt down to a guaranteed 10-point floor.
- ⚡ **Puzzle Rush & Survival (`rush.html`)**: Fast-paced speed sprints (1m, 3m, 5m) and sudden-death Survival mode against a 3-strike failure limit.
- ⚔️ **1v1 Puzzle Battle Duel (`battle.html`)**: Real-time multiplayer matchmaking with a 60-second window pairing players within $\pm$200 Elo points. Features live bidirectional room polling (every 1.5s), a synchronized split-HUD race bar, countdown clock, and an automated Challenger Bot sparring fallback.
- 🏆 **Multi-Leaderboards (`leaderboard.html`)**: Filterable rankings across Puzzle Rush High Scores, 1v1 Battle Elo Ratings, and Classic Ladder Points, featuring top-three podium medals and personal rank calculations.
- 🧪 **Comprehensive CLI Test Runner (`tests/run_tests.php`)**: Automated test suite containing 33 unit and thematic integration tests validating all four themes.

---

## Quick Setup & Local Execution

### 1. Prerequisites
- **PHP** 8.1 or higher (with PDO MySQL extension enabled)
- **MySQL / MariaDB** server (e.g., via XAMPP, Laragon, or standalone service)

### 2. Database Configuration
1. Create a MySQL database named `puzzle_arena` (or configure your credentials in `.env`):
   ```sql
   CREATE DATABASE puzzle_arena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Run the database migration script to generate the schema and seed the demonstration accounts:
   ```bash
   php sql/migrate.php
   ```

### 3. Start Frontend + Backend (one command)
Double-click `start.bat`, or run it from a terminal in the project root:
```bat
start.bat              :: port 8000
start.bat 9000         :: another port
start.bat --reset      :: wipe + recreate tables and demo accounts first (asks to confirm)
```
It runs `tools/preflight.php` (PHP version, extensions, `.env`, MySQL, tables, Freesound key),
then starts PHP's built-in server and opens the browser. One server serves both the
frontend (`/frontend/`) and the JSON API (`/api/`) from the same origin, so no CORS setup is needed.
Only `public/` is the web root: `.env`, `app/`, `sql/`, `tests/` and `.git/` cannot be requested from a browser.

Manual equivalent:
```bash
php tools/preflight.php
php -S localhost:8000 -t public
```

### 4. Access the Application
The launcher opens this automatically:
```
http://localhost:8000/frontend/index.html
```

---

## Pre-Seeded Demonstration Accounts

For the assignment evaluation, two accounts are pre-seeded in the database to allow immediate testing of real-time 1v1 multiplayer matchmaking across two separate browser sessions:

| Account | Username | Password | Starting Elo | Purpose |
|---|---|---|---|---|
| **Player A** | `pilot_player` | `password123` | 500 Elo | Primary player browser |
| **Player B** | `rival_player` | `password123` | 515 Elo | Rival player in separate window/incognito |

### How to Demonstrate 1v1 Multiplayer Matchmaking:
1. Open Browser 1 (e.g., Chrome) and log in as `pilot_player`.
2. Navigate to **1v1 Puzzle Battle** and click the green queue button. The search modal will appear: *"Looking for players near ~500 Elo (±200 rating range)"*.
3. Open Browser 2 (e.g., Chrome Incognito or Firefox) and log in as `rival_player`.
4. Navigate to **1v1 Puzzle Battle** and join the queue.
5. Within 1.5 seconds, the matchmaking engine pairs both players into an active duel room:
   - Each browser shows the opponent's username and rating in the Split-HUD.
   - Submitting guesses instantly updates the central Race Bar and strikes indicators across both browsers.

---

## Coursework Themes & Technical Defense

### Theme 1: Virtual Identity
- **Implementation:**
  - Secure credential registration (`api/register.php`) and authentication (`api/login.php`) using PHP's native `password_hash()` with the `PASSWORD_BCRYPT` algorithm ($2y$ prefix, cost factor 12).
  - Immunity against Session Fixation attacks: calls `session_regenerate_id(true)` immediately upon successful credential validation to invalidate old session tokens.
  - Relational identity persistence: the `users` table securely holds the player's hashed password, `ladder_node`, `battle_rating` (default 500 Elo), and high scores.
- **Alternative Design:** Storing MD5 or SHA-256 hashes.
- **Defense & Trade-Off:** MD5 and unsalted SHA-256 are vulnerable to modern GPU rainbow-table attacks. Bcrypt incorporates automatic unique salt generation and an intentional computational cost parameter that slows down brute-force attacks.

---

### Theme 2: Interoperability
- **Implementation:**
  - **Server-Side API Proxy (`api/puzzle.php`):** Communicates with the third-party Banana Riddle API via server-side cURL. Prevents browser CORS restrictions and hides the solution from the client.
  - **Anti-Cheat Tokenization:** The remote puzzle solution is stored securely in the PHP server session (`$_SESSION['current_solution']`) and stripped from the response returned to the client. The client receives only the puzzle image URL.
  - **In-Memory Session Cache (`src/puzzle_cache.php`):** Prefetches puzzles into session memory, reducing puzzle load latency to sub-10ms.
  - **RESTful Elo Matchmaking Engine (`api/battle.php`):** Exchanges structured JSON contracts. Evaluates candidate players in the `matchmaking_queue` within a $|Rating_A - Rating_B| \le 200$ tolerance window, filtering out stale entries using a 10-second heartbeat TTL.
- **Alternative Design:** Direct client-side fetch from the browser to the third-party riddle API.
- **Defense & Trade-Off:** Direct browser calls expose the API endpoint and solution payload to client inspection tools (DevTools Network tab), enabling trivial cheating. The server-side proxy acts as a secure boundary and enforcement layer.

---

### Theme 3: Event Handling
- **Implementation:**
  - **Mistake Decay State Machine (Classic Ladder):** Tracks attempts per node. A `MistakeEvent` chips 15 points off the potential reward ($50 \to 35 \to 20 \to 10$). A `SolveEvent` commits the final score and unlocks the next ladder rung.
  - **Strikeout Events (Puzzle Rush & Battle):** Wrong answers trigger strike box animations (`p-strike-X`). Three strikes emit a `GameOverEvent`, terminating the run and persisting the score.
  - **Bidirectional Duel Synchronization (Puzzle Battle):** Periodically dispatches `room_sync` REST events (every 1.5s) to coordinate opponent scores, strikes, and dynamic race-bar percentages across both clients.
  - **Native DOM Event Dispatchers:** Uses standard `addEventListener` hooks for the 0–9 digit keypad and form submissions without external UI libraries.
- **Alternative Design:** Persistent WebSocket event stream (e.g., Ratchet or Node.js).
- **Defense & Trade-Off:** WebSockets require persistent background daemon processes that can be fragile to deploy and manage in shared PHP/Apache hosting environments. Standard REST polling with lightweight JSON payloads achieves the exact same real-time gameplay while remaining 100% portable and defensible.

---

### Theme 4: Version Control
- **Implementation:**
  - Structured Git feature-branch workflow where every major capability was developed on an isolated branch before merging:
    - `feature/banana-puzzle-arena`
    - `feature/classic-ladder`
    - `feature/puzzle-rush`
    - `feature/puzzle-battle`
    - `feature/leaderboards`
  - Automated test harness (`tests/run_tests.php`) validating each theme contract systematically.
- **Alternative Design:** Direct commits onto the `main` branch.
- **Defense & Trade-Off:** Committing directly to `main` increases the risk of regression and breaks traceable code review evidence. The feature-branch model provides clear audit trails, isolated testing, and reproducible milestones.

---

## Automated Test Harness

The project includes an automated test runner verifying all 33 architectural assertions across the four themes.

### Run All 33 Tests:
```bash
php tests/run_tests.php
```

### Run Tests by Individual Academic Theme:
```bash
# Theme 1: Virtual Identity (Bcrypt, Sessions, Database Persistence)
php tests/run_tests.php --theme=identity

# Theme 2: Interoperability (API Proxy, Session Cache, Elo Matchmaking)
php tests/run_tests.php --theme=interop

# Theme 3: Event Handling (Chip Scoring, Strikeouts, Duel Synchronization)
php tests/run_tests.php --theme=events
```

---

## Project Structure

```
├── public/                   # WEB ROOT: the only folder a browser can reach
│   ├── api/                # Thin entry points: run middleware, call one controller
│   │   ├── answer.php  battle.php  dev_watch.php  encouragement.php  ladder.php
│   │   ├── leaderboard.php  login.php  logout.php  me.php  puzzle.php  register.php
│   │   └── rush.php  sounds.php  sounds_preload.php  warmup.php
│   └── frontend/
│       ├── arena.html          # Main Arena navigation hub
│       ├── battle.html         # 1v1 real-time Puzzle Battle duel arena
│       ├── classic.html        # Classic Ladder adventure mode
│       ├── index.html          # Coursework demonstration & feature showcase
│       ├── leaderboard.html    # Ranked podium leaderboards
│       ├── login.html          # User login and registration form
│       ├── rush.html           # Puzzle Rush and Survival modes
│       ├── style.css           # Global dark arena styles
│       └── js/
│           ├── api.js          # Shared fetch client and puzzle cache consumer
│           ├── battle.js       # Battle duel controller & room sync loop
│           ├── classic.js      # Classic ladder state machine
│           ├── leaderboard.js  # Leaderboard tabs and rank renderer
│           └── rush.js         # Puzzle rush sprint timer and strikes handler
├── app/
│   ├── bootstrap.php       # Loads every class below in dependency order
│   ├── Core/
│   │   ├── Env.php         # Single .env reader (was duplicated in 3 files)
│   │   ├── Database.php    # PDO connection singleton
│   │   ├── Request.php     # HTTP method + JSON body helpers
│   │   └── Response.php    # JSON response helpers
│   ├── Middleware/
│   │   ├── SessionMiddleware.php  # Secure cookie flags + 30 min inactivity timeout
│   │   ├── AuthMiddleware.php     # requireLogin() -> 401
│   │   └── MethodMiddleware.php   # allow('POST') -> 405 + Allow header
│   ├── Services/           # Business rules, SQL and third-party API calls
│   │   ├── AuthService.php  ProfileService.php  LadderService.php  RushService.php
│   │   ├── MatchmakingService.php  BattleService.php  LeaderboardService.php
│   │   ├── PuzzleCacheService.php (Banana API)  FreesoundService.php
│   │   └── EncouragementService.php (affirmations.dev)  DevWatchService.php
│   └── Controllers/        # Read request, validate, call services, send JSON
│       ├── AuthController.php  PuzzleController.php  LadderController.php
│       ├── RushController.php  BattleController.php  LeaderboardController.php
│       └── MediaController.php
├── tools/
│   └── preflight.php       # Environment checks used by start.bat
├── start.bat               # One-click launcher (frontend + backend)
├── sql/
│   ├── migrate.php         # Migration runner and test account seeder
│   └── schema.sql          # Relational database table definitions
├── tests/
│   └── run_tests.php       # 33-test automated thematic test runner
├── AGENTS.md               # Student workflow and constraint guidelines
├── README.md               # Project documentation & examination defense guide
└── .env.example            # Environment database credential template
```

---

*Authored for the CIS046-3 Software for Enterprise module assessment.*
