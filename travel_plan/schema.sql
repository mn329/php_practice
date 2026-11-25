-- TabiPlan テーブル作成用 SQL (SQLite)
-- アプリ初回アクセス時に自動で実行されますが、手動で作る場合は
--   sqlite3 data/travel.sqlite < schema.sql

PRAGMA foreign_keys = ON;

-- ユーザー
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- 旅行プラン
CREATE TABLE IF NOT EXISTS plans (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    title       TEXT    NOT NULL,
    destination TEXT    NOT NULL,
    dest_lat    REAL,
    dest_lng    REAL,
    dest_country TEXT   NOT NULL DEFAULT '',
    start_date  TEXT    NOT NULL,
    end_date    TEXT    NOT NULL,
    budget      INTEGER NOT NULL DEFAULT 0,
    memo        TEXT    NOT NULL DEFAULT '',
    cover_image TEXT,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 日程（スケジュール）
CREATE TABLE IF NOT EXISTS items (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id    INTEGER NOT NULL,
    day        INTEGER NOT NULL,
    time       TEXT    NOT NULL DEFAULT '',
    place      TEXT    NOT NULL,
    memo       TEXT    NOT NULL DEFAULT '',
    cost       INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    lat        REAL,
    lng        REAL,
    created_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

-- 持ち物チェックリスト
CREATE TABLE IF NOT EXISTS checklist (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id    INTEGER NOT NULL,
    label      TEXT    NOT NULL,
    is_done    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_plans_user ON plans(user_id);
CREATE INDEX IF NOT EXISTS idx_items_plan ON items(plan_id);
CREATE INDEX IF NOT EXISTS idx_checklist_plan ON checklist(plan_id);
