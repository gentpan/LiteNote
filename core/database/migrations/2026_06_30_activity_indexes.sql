CREATE INDEX IF NOT EXISTS idx_activities_visibility_happened ON activities(visibility, happened_at DESC);
