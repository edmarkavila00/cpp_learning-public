USE cpp_learning;

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT NOT NULL PRIMARY KEY,
    display_name VARCHAR(32) NOT NULL,
    avatar_id TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bio VARCHAR(160) NOT NULL DEFAULT '',
    photo_path VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SET @profile_photo_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'photo_path'
);
SET @profile_photo_migration = IF(
    @profile_photo_column_exists = 0,
    'ALTER TABLE user_profiles ADD COLUMN photo_path VARCHAR(255) NULL AFTER bio',
    'SELECT 1'
);
PREPARE profile_photo_statement FROM @profile_photo_migration;
EXECUTE profile_photo_statement;
DEALLOCATE PREPARE profile_photo_statement;

SET @profile_cover_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'user_profiles'
      AND column_name = 'cover_path'
);
SET @profile_cover_migration = IF(
    @profile_cover_column_exists = 0,
    'ALTER TABLE user_profiles ADD COLUMN cover_path VARCHAR(255) NULL AFTER photo_path',
    'SELECT 1'
);
PREPARE profile_cover_statement FROM @profile_cover_migration;
EXECUTE profile_cover_statement;
DEALLOCATE PREPARE profile_cover_statement;

INSERT IGNORE INTO user_profiles (user_id, display_name, avatar_id)
SELECT id, username, MOD(id - 1, 12)
FROM users;

CREATE TABLE IF NOT EXISTS code_game_progress (
    user_id INT NOT NULL,
    problem_id VARCHAR(64) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    solved TINYINT(1) NOT NULL DEFAULT 0,
    solved_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, problem_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS paste_penalties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    game_type VARCHAR(24) NOT NULL,
    game_id VARCHAR(64) NOT NULL,
    xp_deducted SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_paste_penalties_user_created (user_id, created_at)
);

CREATE TABLE IF NOT EXISTS user_xp_purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_type VARCHAR(32) NOT NULL,
    context_id INT NOT NULL,
    item_key VARCHAR(64) NOT NULL,
    xp_spent INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_xp_purchase (user_id, item_type, context_id, item_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_badges (
    user_id INT NOT NULL,
    badge_key VARCHAR(40) NOT NULL,
    earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS adventure_progress (
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    cleared TINYINT(1) NOT NULL DEFAULT 0,
    cleared_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, challenge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS coop_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invite_code CHAR(8) NOT NULL UNIQUE,
    host_id INT NOT NULL,
    challenge_id INT NOT NULL,
    mode ENUM('team_answer', 'chat_room', 'race') NOT NULL,
    status ENUM('open', 'completed') NOT NULL DEFAULT 'open',
    winner_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_coop_rooms_host_status (host_id, status)
);

CREATE TABLE IF NOT EXISTS coop_room_members (
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES coop_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_coop_members_user (user_id, joined_at)
);

CREATE TABLE IF NOT EXISTS coop_room_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    message VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES coop_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_coop_messages_room_id (room_id, id)
);

CREATE TABLE IF NOT EXISTS challenge_reviews (
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    interval_days SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    due_at DATE NOT NULL,
    last_reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, challenge_id),
    INDEX idx_challenge_reviews_due (user_id, due_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS challenge_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    issue_type VARCHAR(32) NOT NULL,
    details VARCHAR(1000) NOT NULL DEFAULT '',
    status ENUM('open', 'reviewed', 'resolved') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_challenge_reports_status_created (status, created_at),
    INDEX idx_challenge_reports_user (user_id, challenge_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS study_groups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT NOT NULL,
    name VARCHAR(60) NOT NULL,
    invite_code CHAR(8) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_study_groups_owner (owner_id, created_at),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS study_group_members (
    group_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    INDEX idx_study_group_members_user (user_id, joined_at),
    FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS study_group_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id INT UNSIGNED NOT NULL,
    challenge_id INT NOT NULL,
    due_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_study_group_challenge (group_id, challenge_id),
    INDEX idx_study_group_assignments_due (group_id, due_at),
    FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
);
