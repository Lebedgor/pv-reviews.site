-- Idempotent migration for existing deployments (2026-09):
-- renewals + subscriptions. Run once via sqlite3 pvr.db < setup-migration.sql

ALTER TABLE licenses ADD COLUMN ls_subscription_id TEXT;

CREATE TABLE IF NOT EXISTS resend_throttle (
    domain TEXT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
