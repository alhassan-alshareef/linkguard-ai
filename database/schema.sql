CREATE TABLE IF NOT EXISTS analyses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id TEXT NOT NULL UNIQUE,
    submitted_url TEXT NOT NULL,
    normalized_url TEXT NOT NULL,
    host TEXT NOT NULL,
    scheme TEXT NOT NULL,
    risk_score INTEGER NOT NULL,
    risk_level TEXT NOT NULL,
    report_json TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_analyses_created_at ON analyses(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_analyses_risk_level ON analyses(risk_level);
