-- CTF Platform Seed Data
-- Run this after schema.sql

-- Default admin account (password: admin123 - CHANGE IN PRODUCTION)
-- bcrypt hash of 'admin123' with 12 rounds
INSERT INTO teams (name, username, password_hash, role, is_active)
VALUES (
    'Platform Admin',
    'admin',
    '$2a$12$4vRI932BzKkxJMIeJl/yBuMgUyeYvhkgOyCgSlvBPTkG4BK3m6LuC',
    'super_admin',
    true
) ON CONFLICT (username) DO NOTHING;

-- Default event
INSERT INTO events (name, is_live, is_frozen, is_archived, maintenance_mode)
VALUES ('NFSU CTF 2026', false, false, false, false);

-- Default rules/info content
INSERT INTO site_content (content_key, content_value) VALUES
('rules', '## Competition Rules

### Flag Format
- All flags follow the format: `NFSUCTF{...}`
- Flags are **case-sensitive**
- Leading and trailing whitespace is automatically trimmed
- Exact match is required

### Scoring
- Each challenge has a fixed point value
- Points are awarded upon first correct submission
- Duplicate submissions for solved challenges are ignored

### Tie-Breaking
1. Higher total points wins
2. If tied, the team with the earlier last solve time wins
3. If still tied, the team with more total solves wins

### Conduct Policy
- No brute forcing or automated exploitation of platform infrastructure
- No sharing of flags between teams
- No attacking the competition infrastructure
- Admin decisions are final

### Support
Contact the organizers if you encounter any issues with the platform.'),

('info', '## About NFSU CTF 2026

Welcome to the National Forensic Sciences University Capture The Flag competition!

This is a team-based cybersecurity competition where participants solve challenges across various categories including:

- **Web Exploitation**
- **Cryptography**
- **Reverse Engineering**
- **Forensics**
- **Binary Exploitation**
- **Miscellaneous**

### Event Schedule
Check the dashboard for current event timing and status.

### Getting Started
1. Log in with your team credentials
2. Navigate to the Challenges page
3. Read challenge descriptions carefully
4. Submit flags in the format `NFSUCTF{your_flag_here}`
5. Track your progress on the Leaderboard')
ON CONFLICT (content_key) DO NOTHING;
