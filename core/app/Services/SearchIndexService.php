<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Enums\PostStatus;
use App\Enums\Toggle;
use App\Models\Music;
use App\Models\Page;
use App\Models\Post;
use App\Models\Talk;

/**
 * SQLite FTS5 全文索引：搜索时避免全表 LIKE + 内存合并。
 */
final class SearchIndexService
{
  private static bool $ready = false;

  public static function install(): void
  {
    if (self::$ready) {
      return;
    }
    MigrationRunner::run();
    self::$ready = true;
    if (self::available() && self::count() === 0) {
      self::rebuild();
    }
  }

  public static function invalidate(): void
  {
    // FTS 由 sync* / remove 增量维护，无文件缓存。
  }

  public static function syncPost(Post $post, string $markdown = ''): void
  {
    self::install();
    $id = (int)$post->id;
    if ($id <= 0) {
      return;
    }
    if ((string)$post->status !== PostStatus::Published->value || (int)($post->is_private ?? 0) === 1) {
      self::remove('post', $id);
      return;
    }
    if ($markdown === '') {
      $markdown = PostContentStorage::bodyWithoutTitleHeading($post->markdown(), (string)$post->title);
    }
    self::upsert('post', $id, (string)$post->title, trim((string)$post->summary . "\n" . $markdown));
  }

  public static function syncPage(Page $page): void
  {
    self::install();
    $id = (int)$page->id;
    if ($id <= 0) {
      return;
    }
    self::upsert('page', $id, (string)$page->title, trim((string)($page->content ?? '') . "\n" . (string)($page->markdown_content ?? '')));
  }

  public static function syncTalk(Talk $talk): void
  {
    self::install();
    $id = (int)$talk->id;
    if ($id <= 0) {
      return;
    }
    if ((int)($talk->is_public ?? 0) !== Toggle::On->value) {
      self::remove('talk', $id);
      return;
    }
    self::upsert('talk', $id, '滔客 #' . $id, trim((string)($talk->content ?? '') . "\n" . (string)($talk->mood ?? '')));
  }

  public static function syncMusic(Music $music): void
  {
    self::install();
    $id = (int)$music->id;
    if ($id <= 0) {
      return;
    }
    self::upsert(
      'music',
      $id,
      (string)$music->title,
      trim((string)($music->artist ?? '') . "\n" . (string)($music->album ?? '') . "\n" . (string)($music->lyrics ?? ''))
    );
  }

  public static function syncXTweet(int $id): void
  {
    self::install();
    if ($id <= 0) {
      return;
    }
    try {
      $row = Post::db()->fetchOne('SELECT * FROM x_tweets WHERE id = ? LIMIT 1', [$id]);
    } catch (\Throwable) {
      return;
    }
    if (!$row || (int)($row['is_public'] ?? 0) !== Toggle::On->value) {
      self::remove('x', $id);
      return;
    }
    self::upsert(
      'x',
      $id,
      'X #' . $id,
      trim((string)($row['content'] ?? '') . "\n" . (string)($row['tweet_author_name'] ?? '') . "\n" . (string)($row['tweet_author_handle'] ?? ''))
    );
  }

  public static function available(): bool
  {
    try {
      $row = Database::getInstance()->fetchOne(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='search_index'"
      );
      return $row !== null;
    } catch (\Throwable) {
      return false;
    }
  }

  public static function count(): int
  {
    if (!self::available()) {
      return 0;
    }
    return (int)Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM search_index');
  }

  public static function rebuild(): void
  {
    self::install();
    if (!self::available()) {
      return;
    }

    $db = Database::getInstance();
    $db->query('DELETE FROM search_index');

    foreach (Post::db()->fetchAll(
      "SELECT id, title, slug, summary, status, is_private FROM posts WHERE status = ? AND COALESCE(is_private, 0) = 0",
      [PostStatus::Published->value]
    ) as $row) {
      self::upsert(
        'post',
        (int)$row['id'],
        (string)$row['title'],
        trim((string)$row['summary'] . "\n" . PostContentStorage::read((string)$row['slug']))
      );
    }

    foreach (Page::db()->fetchAll('SELECT id, title, content, markdown_content FROM pages') as $row) {
      self::upsert(
        'page',
        (int)$row['id'],
        (string)$row['title'],
        trim((string)($row['content'] ?? '') . "\n" . (string)($row['markdown_content'] ?? ''))
      );
    }

    foreach (Talk::db()->fetchAll(
      'SELECT id, content, mood FROM talk WHERE is_public = ?',
      [Toggle::On->value]
    ) as $row) {
      self::upsert(
        'talk',
        (int)$row['id'],
        '滔客 #' . (int)$row['id'],
        trim((string)($row['content'] ?? '') . "\n" . (string)($row['mood'] ?? ''))
      );
    }

    foreach (Music::db()->fetchAll('SELECT id, title, artist, album, lyrics FROM music') as $row) {
      self::upsert(
        'music',
        (int)$row['id'],
        (string)$row['title'],
        trim((string)($row['artist'] ?? '') . "\n" . (string)($row['album'] ?? '') . "\n" . (string)($row['lyrics'] ?? ''))
      );
    }

    try {
      foreach (Post::db()->fetchAll(
        'SELECT id, content, tweet_author_name, tweet_author_handle FROM x_tweets WHERE is_public = ?',
        [Toggle::On->value]
      ) as $row) {
        self::upsert(
          'x',
          (int)$row['id'],
          'X #' . (int)$row['id'],
          trim((string)($row['content'] ?? '') . "\n" . (string)($row['tweet_author_name'] ?? '') . "\n" . (string)($row['tweet_author_handle'] ?? ''))
        );
      }
    } catch (\Throwable) {
    }
  }

  public static function upsert(string $type, int $id, string $title, string $body): void
  {
    if (!self::available()) {
      return;
    }

    $db = Database::getInstance();
    $db->query(
      'DELETE FROM search_index WHERE entity_type = ? AND entity_id = ?',
      [$type, $id]
    );
    if (trim($title . $body) === '') {
      return;
    }

    $db->query(
      'INSERT INTO search_index(entity_type, entity_id, title, body) VALUES (?, ?, ?, ?)',
      [$type, $id, $title, $body]
    );
  }

  public static function remove(string $type, int $id): void
  {
    if (!self::available()) {
      return;
    }
    Database::getInstance()->query(
      'DELETE FROM search_index WHERE entity_type = ? AND entity_id = ?',
      [$type, $id]
    );
  }

  /**
   * @return array{items: array<int, array{entity_type:string, entity_id:int, title:string}>, total:int}
   */
  public static function search(string $keyword, int $limit = 50, int $offset = 0): array
  {
    self::install();
    if (!self::available()) {
      return ['items' => [], 'total' => 0];
    }

    $keyword = trim($keyword);
    if ($keyword === '' || mb_strlen($keyword) > 100) {
      return ['items' => [], 'total' => 0];
    }

    $match = self::ftsQuery($keyword);
    if ($match === '') {
      return ['items' => [], 'total' => 0];
    }

    $db = Database::getInstance();
    $total = (int)$db->fetchColumn(
      'SELECT COUNT(*) FROM search_index WHERE search_index MATCH ?',
      [$match]
    );
    $rows = $db->fetchAll(
      'SELECT entity_type, entity_id, title
       FROM search_index
       WHERE search_index MATCH ?
       ORDER BY rank
       LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
      [$match]
    );

    return ['items' => $rows, 'total' => $total];
  }

  private static function ftsQuery(string $keyword): string
  {
    $parts = preg_split('/\s+/u', trim($keyword)) ?: [];
    $quoted = [];
    foreach ($parts as $part) {
      $part = trim((string)$part);
      if ($part === '') {
        continue;
      }
      $part = str_replace('"', '""', $part);
      $quoted[] = '"' . $part . '"';
    }
    return implode(' ', $quoted);
  }
}
