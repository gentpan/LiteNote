CREATE VIRTUAL TABLE IF NOT EXISTS search_index USING fts5(
  entity_type,
  entity_id UNINDEXED,
  title,
  body,
  tokenize = 'unicode61'
);
