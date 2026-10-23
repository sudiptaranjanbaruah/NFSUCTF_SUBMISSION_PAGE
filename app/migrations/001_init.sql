-- NFSU Guwahati CTF — initial schema (MySQL 8 / MariaDB 10.4+)

CREATE TABLE settings (
    k VARCHAR(64) PRIMARY KEY,
    v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    invite_code VARCHAR(16) NOT NULL,
    captain_id INT UNSIGNED NULL,
    affiliation VARCHAR(120) NULL,
    country VARCHAR(64) NULL,
    is_solo TINYINT(1) NOT NULL DEFAULT 0,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    is_banned TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teams_name (name),
    UNIQUE KEY uq_teams_code (invite_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL,
    email VARCHAR(190) NOT NULL,
    full_name VARCHAR(120) NULL,
    password_hash VARCHAR(255) NULL,
    role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
    team_id INT UNSIGNED NULL,
    affiliation VARCHAR(120) NULL,
    country VARCHAR(64) NULL,
    email_verified_at DATETIME NULL,
    is_banned TINYINT(1) NOT NULL DEFAULT 0,
    announcements_seen_at DATETIME NULL,
    last_login_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_team (team_id),
    CONSTRAINT fk_users_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE teams ADD CONSTRAINT fk_teams_captain FOREIGN KEY (captain_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE oauth_identities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider VARCHAR(20) NOT NULL,
    provider_uid VARCHAR(128) NOT NULL,
    email VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_oauth (provider, provider_uid),
    CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_otps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    purpose VARCHAR(20) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_lookup (email, purpose, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    full_name VARCHAR(120) NULL,
    team_name VARCHAR(64) NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invite_token (token_hash),
    KEY idx_invite_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(48) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_cat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE challenges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    description MEDIUMTEXT NULL,
    category_id INT UNSIGNED NOT NULL,
    difficulty ENUM('easy','medium','hard','insane') NOT NULL DEFAULT 'easy',
    author VARCHAR(64) NULL,
    state ENUM('draft','hidden','visible') NOT NULL DEFAULT 'draft',
    release_at DATETIME NULL,
    scoring ENUM('static','dynamic') NOT NULL DEFAULT 'static',
    points INT NOT NULL DEFAULT 100,
    min_points INT NOT NULL DEFAULT 50,
    decay INT NOT NULL DEFAULT 20,
    max_attempts INT NULL,
    connection_info VARCHAR(255) NULL,
    tags VARCHAR(255) NULL,
    requires_id INT UNSIGNED NULL,
    first_blood_bonus VARCHAR(64) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ch_state (state),
    CONSTRAINT fk_ch_cat FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_ch_req FOREIGN KEY (requires_id) REFERENCES challenges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT UNSIGNED NOT NULL,
    type ENUM('static','regex') NOT NULL DEFAULT 'static',
    content VARCHAR(512) NOT NULL,
    case_insensitive TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_flag_ch FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE challenge_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_file_ch FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    cost INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_hint_ch FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hint_unlocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hint_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hint_team (hint_id, team_id),
    CONSTRAINT fk_hu_hint FOREIGN KEY (hint_id) REFERENCES hints(id) ON DELETE CASCADE,
    CONSTRAINT fk_hu_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE solves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    challenge_id INT UNSIGNED NOT NULL,
    points_awarded INT NOT NULL,
    bonus_points INT NOT NULL DEFAULT 0,
    solve_rank INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_solve (team_id, challenge_id),
    KEY idx_solve_ch (challenge_id, created_at),
    KEY idx_solve_time (created_at),
    CONSTRAINT fk_solve_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_solve_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_solve_ch FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    challenge_id INT UNSIGNED NOT NULL,
    provided VARCHAR(512) NOT NULL,
    correct TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NULL,
    ua VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sub_team_ch (team_id, challenge_id),
    KEY idx_sub_time (created_at),
    KEY idx_sub_ip (ip),
    CONSTRAINT fk_sub_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_ch FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE score_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    challenge_id INT UNSIGNED NULL,
    type ENUM('unique_solve','writeup','bug_report','penalty','other') NOT NULL DEFAULT 'other',
    delta INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    admin_id INT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_adj_team (team_id),
    CONSTRAINT fk_adj_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_adj_ch FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    body TEXT NOT NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    slug VARCHAR(48) PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    target VARCHAR(190) NULL,
    meta TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_time (created_at),
    KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    method VARCHAR(20) NOT NULL,
    success TINYINT(1) NOT NULL,
    ip VARCHAR(45) NULL,
    ua VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_time (created_at),
    KEY idx_login_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    html MEDIUMTEXT NOT NULL,
    text_body TEXT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mq_pending (sent_at, attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    k CHAR(64) PRIMARY KEY,
    hits INT UNSIGNED NOT NULL,
    reset_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, sort_order) VALUES
('Web', 1), ('Crypto', 2), ('Forensics', 3), ('Reverse', 4), ('Pwn', 5), ('OSINT', 6), ('Misc', 7);

INSERT INTO pages (slug, title, body) VALUES
('rules', 'Rules', '## Flag format\n\nFlags look like `NFSUCTF{...}` unless a challenge says otherwise. Flags are case-sensitive unless stated.\n\n## Scoring\n\n- Each challenge is worth the points shown. Some challenges use **dynamic scoring**: their value drops as more teams solve them.\n- The first three teams to solve a challenge earn a **first-blood bonus**.\n- Unlocking a hint costs the points shown on it.\n- Ties are broken by whoever reached the score first.\n\n## Conduct\n\n- Do not attack the platform or other participants.\n- Do not share flags or solutions with other teams during the event.\n- Do not brute-force flag submission; it is rate-limited and logged.\n- Organisers may award bonus points for unique solutions or write-ups, and may deduct points or disqualify teams for misconduct. Organiser decisions are final.\n'),
('about', 'About', '**NFSU Guwahati CTF** is a jeopardy-style Capture The Flag competition organised at the National Forensic Sciences University, Guwahati Campus.\n\nChallenges cover web security, cryptography, digital forensics, reverse engineering, binary exploitation, OSINT and more.\n');
