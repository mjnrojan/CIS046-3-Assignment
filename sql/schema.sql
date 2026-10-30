-- schema.sql
-- Creates the three tables the game needs.
-- Run once: mysql -u root -p banana_game < sql/schema.sql
-- Or import through phpMyAdmin.

-- Users table: stores login credentials.
-- username is UNIQUE so two players cannot share the same name.
-- password_hash holds a bcrypt string from password_hash(), never plain text.
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    ladder_points INT          NOT NULL DEFAULT 0,
    ladder_node   INT          NOT NULL DEFAULT 1,
    rush_high_score INT        NOT NULL DEFAULT 0,
    battle_rating INT          NOT NULL DEFAULT 500,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Scores table: one row per finished game / run.
-- correct and total let the UI show "you got 3 out of 5".
-- score is separate so we can rank by it without recalculating.
CREATE TABLE IF NOT EXISTS scores (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT      NOT NULL,
    mode      VARCHAR(20) NOT NULL DEFAULT 'rush',
    score     INT      NOT NULL DEFAULT 0,
    correct   INT      NOT NULL DEFAULT 0,
    total     INT      NOT NULL DEFAULT 0,
    played_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Battle matches table: records real-time 1v1 duels.
CREATE TABLE IF NOT EXISTS battle_matches (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    player1_id    INT NOT NULL,
    player2_id    INT NULL,
    player1_score INT NOT NULL DEFAULT 0,
    player2_score INT NOT NULL DEFAULT 0,
    winner_id     INT NULL,
    played_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Progress table: tracks the highest level a player has reached.
-- One row per user. updated_at changes each time the player advances.
CREATE TABLE IF NOT EXISTS progress (
    user_id    INT      NOT NULL,
    level      INT      NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indexes for fast queries across all 3 modes.
CREATE INDEX idx_users_rush ON users(rush_high_score DESC);
CREATE INDEX idx_users_battle ON users(battle_rating DESC);
CREATE INDEX idx_users_ladder ON users(ladder_points DESC);
CREATE INDEX idx_scores_user ON scores(user_id);

