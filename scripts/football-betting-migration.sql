-- Football Betting System Migration
-- Adds support for 1v1, 2v2, and Global betting types

-- Step 1: Add new columns to sports_bets table
ALTER TABLE sports_bets ADD COLUMN bet_category TEXT DEFAULT 'p2p';
ALTER TABLE sports_bets ADD COLUMN opponent_id INTEGER REFERENCES users(id);
ALTER TABLE sports_bets ADD COLUMN pool_id INTEGER;
ALTER TABLE sports_bets ADD COLUMN pool_share REAL DEFAULT 0.0;

-- Step 2: Create football_pools table for 2v2 and Global bets
CREATE TABLE IF NOT EXISTS football_pools (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id            INTEGER NOT NULL REFERENCES sports_matches(id),
    pool_type           TEXT    NOT NULL,
    team_a_selection    TEXT    NOT NULL,
    team_b_selection    TEXT    NOT NULL,
    pool_a_total        REAL    DEFAULT 0.0,
    pool_b_total        REAL    DEFAULT 0.0,
    pool_a_count        INTEGER DEFAULT 0,
    pool_b_count        INTEGER DEFAULT 0,
    admin_cut_amount    REAL    DEFAULT 0.0,
    status              TEXT    DEFAULT 'open',
    winner_team         TEXT,
    settled_at          INTEGER,
    created_at          INTEGER DEFAULT (strftime('%s','now'))
);

-- Step 3: Create football_2v2_teams table for team organization
CREATE TABLE IF NOT EXISTS football_2v2_teams (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    pool_id             INTEGER NOT NULL REFERENCES football_pools(id),
    team_number         INTEGER NOT NULL,
    player_ids          TEXT    NOT NULL,
    status              TEXT    DEFAULT 'open',
    created_at          INTEGER DEFAULT (strftime('%s','now'))
);

-- Step 4: Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_football_pools_match ON football_pools(match_id);
CREATE INDEX IF NOT EXISTS idx_football_pools_status ON football_pools(status);
CREATE INDEX IF NOT EXISTS idx_football_2v2_teams_pool ON football_2v2_teams(pool_id);
CREATE INDEX IF NOT EXISTS idx_sports_bets_category ON sports_bets(bet_category);
CREATE INDEX IF NOT EXISTS idx_sports_bets_pool ON sports_bets(pool_id);
CREATE INDEX IF NOT EXISTS idx_sports_bets_opponent ON sports_bets(opponent_id);
