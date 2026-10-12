-- Design-partner signups from teemops.com. Apply with `npm run db:init`.
-- test/signup.test.ts carries a copy of this; keep the two in step.
CREATE TABLE IF NOT EXISTS design_partner_leads (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT NOT NULL,
  email      TEXT NOT NULL,
  company    TEXT,
  aws_scale  TEXT,
  notes      TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
