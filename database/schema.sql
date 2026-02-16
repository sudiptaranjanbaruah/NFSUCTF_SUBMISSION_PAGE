-- CTF Platform Database Schema
-- Run this against your Supabase PostgreSQL instance

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================
-- EVENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    start_time TIMESTAMPTZ,
    end_time TIMESTAMPTZ,
    is_live BOOLEAN DEFAULT false,
    is_frozen BOOLEAN DEFAULT false,
    is_archived BOOLEAN DEFAULT false,
    maintenance_mode BOOLEAN DEFAULT false,
    frozen_leaderboard JSONB DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- TEAMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS teams (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    total_points INTEGER DEFAULT 0,
    last_solve_time TIMESTAMPTZ DEFAULT NULL,
    total_solves INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    role VARCHAR(20) DEFAULT 'team' CHECK (role IN ('team', 'admin', 'super_admin')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_teams_total_points ON teams(total_points DESC);
CREATE INDEX idx_teams_role ON teams(role);
CREATE INDEX idx_teams_username ON teams(username);

-- ============================================
-- CHALLENGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS challenges (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL,
    points INTEGER NOT NULL DEFAULT 100,
    flag_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    max_attempts INTEGER DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_challenges_category ON challenges(category);
CREATE INDEX idx_challenges_is_active ON challenges(is_active);

-- ============================================
-- CHALLENGE FILES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS challenge_files (
    id SERIAL PRIMARY KEY,
    challenge_id INTEGER NOT NULL REFERENCES challenges(id) ON DELETE CASCADE,
    file_name VARCHAR(255) NOT NULL,
    file_url TEXT NOT NULL,
    file_size INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_challenge_files_challenge_id ON challenge_files(challenge_id);

-- ============================================
-- SOLVES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS solves (
    id SERIAL PRIMARY KEY,
    team_id UUID NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    challenge_id INTEGER NOT NULL REFERENCES challenges(id) ON DELETE CASCADE,
    points_awarded INTEGER NOT NULL,
    solved_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(team_id, challenge_id)
);

CREATE INDEX idx_solves_team_id ON solves(team_id);
CREATE INDEX idx_solves_challenge_id ON solves(challenge_id);
CREATE INDEX idx_solves_solved_at ON solves(solved_at);

-- ============================================
-- SUBMISSIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS submissions (
    id SERIAL PRIMARY KEY,
    team_id UUID NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    challenge_id INTEGER NOT NULL REFERENCES challenges(id) ON DELETE CASCADE,
    submitted_flag TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT false,
    ip_address VARCHAR(45),
    user_agent TEXT,
    submitted_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_submissions_team_id ON submissions(team_id);
CREATE INDEX idx_submissions_challenge_id ON submissions(challenge_id);
CREATE INDEX idx_submissions_submitted_at ON submissions(submitted_at);

-- ============================================
-- ANNOUNCEMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_pinned BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- ADMIN LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS admin_logs (
    id SERIAL PRIMARY KEY,
    admin_id UUID REFERENCES teams(id) ON DELETE SET NULL,
    action VARCHAR(255) NOT NULL,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_admin_logs_action ON admin_logs(action);
CREATE INDEX idx_admin_logs_created_at ON admin_logs(created_at);

-- ============================================
-- SITE CONTENT TABLE (for editable rules/info)
-- ============================================
CREATE TABLE IF NOT EXISTS site_content (
    id SERIAL PRIMARY KEY,
    content_key VARCHAR(100) NOT NULL UNIQUE,
    content_value TEXT NOT NULL,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
