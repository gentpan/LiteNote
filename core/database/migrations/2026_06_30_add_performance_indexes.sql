CREATE INDEX IF NOT EXISTS idx_posts_status_published_at ON posts(status, published_at DESC);
CREATE INDEX IF NOT EXISTS idx_posts_category_status ON posts(category_id, status);
CREATE INDEX IF NOT EXISTS idx_talk_public_published ON talk(is_public, published_at DESC);
CREATE INDEX IF NOT EXISTS idx_comments_post_status ON comments(post_id, status);
CREATE INDEX IF NOT EXISTS idx_comments_talk_status ON comments(talk_id, status);
CREATE INDEX IF NOT EXISTS idx_comments_music_status ON comments(music_id, status);
