-- schema.sql
-- Creates the three tables the game needs.
-- Run once: mysql -u root -p banana_game < sql/schema.sql
-- Or import through phpMyAdmin.

-- Users table: stores login credentials.
-- username is UNIQUE so two players cannot share the same name.
-- password_hash holds a bcrypt string from password_hash(), never plain text.
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Scores table: one row per finished game.
-- correct and total let the UI show "you got 3 out of 5".
-- score is separate so we can rank by it without recalculating.
CREATE TABLE IF NOT EXISTS scores (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT      NOT NULL,
    score     INT      NOT NULL DEFAULT 0,
    correct   INT      NOT NULL DEFAULT 0,
    total     INT      NOT NULL DEFAULT 0,
    played_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Indexes for queries the app will run often.
-- idx_scores_user speeds up "show my scores" (WHERE user_id = ?).
-- idx_scores_rank speeds up the leaderboard (ORDER BY score DESC LIMIT 10).
CREATE INDEX idx_scores_user ON scores(user_id);
CREATE INDEX idx_scores_rank ON scores(score DESC);
