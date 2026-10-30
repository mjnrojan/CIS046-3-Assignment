-- schema.sql
-- Drops and recreates the complete Banana Puzzle Arena schema.
-- Run once in MySQL / phpMyAdmin, or execute: php sql/migrate.php

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS battle_rooms;
DROP TABLE IF EXISTS matchmaking_queue;
DROP TABLE IF EXISTS battle_matches;
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS progress;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users table: stores login credentials, ratings, and ladder progression.
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    ladder_points   INT          NOT NULL DEFAULT 0,
    ladder_node     INT          NOT NULL DEFAULT 1,
    rush_high_score INT          NOT NULL DEFAULT 0,
    battle_rating   INT          NOT NULL DEFAULT 500,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Scores table: records finished game runs for history and leaderboards.
CREATE TABLE scores (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT         NOT NULL,
    mode      VARCHAR(20) NOT NULL DEFAULT 'rush',
    score     INT         NOT NULL DEFAULT 0,
    correct   INT         NOT NULL DEFAULT 0,
    total     INT         NOT NULL DEFAULT 0,
    played_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Battle matches table: records historical real-time 1v1 duels.
CREATE TABLE battle_matches (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    player1_id    INT NOT NULL,
    player2_id    INT NULL,
    player1_score INT NOT NULL DEFAULT 0,
    player2_score INT NOT NULL DEFAULT 0,
    winner_id     INT NULL,
    played_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Matchmaking queue table: tracks players currently searching for a live duel.
CREATE TABLE matchmaking_queue (
    user_id     INT         PRIMARY KEY,
    rating      INT         NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'searching',
    room_id     VARCHAR(64) NULL,
    joined_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_queue_lookup (status, updated_at, rating),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Battle rooms table: synchronizes active live duels between two connected browsers.
CREATE TABLE battle_rooms (
    room_id         VARCHAR(64) PRIMARY KEY,
    player1_id      INT         NOT NULL,
    player2_id      INT         NOT NULL,
    player1_score   INT         NOT NULL DEFAULT 0,
    player2_score   INT         NOT NULL DEFAULT 0,
    player1_strikes INT         NOT NULL DEFAULT 0,
    player2_strikes INT         NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    winner_id       INT         NULL,
    started_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Progress table: tracks classic ladder milestones.
CREATE TABLE progress (
    user_id    INT      NOT NULL,
    level      INT      NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indexes for sub-millisecond leaderboard queries
CREATE INDEX idx_users_rush ON users(rush_high_score DESC);
CREATE INDEX idx_users_battle ON users(battle_rating DESC);
CREATE INDEX idx_users_ladder ON users(ladder_points DESC);
CREATE INDEX idx_scores_user ON scores(user_id);
