<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\ActivityDatabase;
use App\Services\ActivityInstaller;

final class Activity
{
    private array $attributes = [];

    /**
     * 瞬态标志:本次 record() 是新建(true)还是更新(false)。
     * 声明为真实公有属性,绕过 __set,不会写入 $attributes、不入库。
     */
    public bool $wasRecentlyCreated = false;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function db(): ActivityDatabase
    {
        ActivityInstaller::install();
        return ActivityDatabase::getInstance();
    }

    public static function find(int $id): ?self
    {
        $row = self::db()->fetchOne('SELECT * FROM activities WHERE id = ? LIMIT 1', [$id]);
        return $row ? new self($row) : null;
    }

    public static function paginate(int $page, int $perPage, array $filters = [], bool $publicOnly = false): array
    {
        $where = [];
        $params = [];
        if ($publicOnly) {
            $where[] = "visibility = 'public'";
        }
        foreach (['type', 'source', 'visibility'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "{$key} = ?";
                $params[] = (string)$filters[$key];
            }
        }
        if (!empty($filters['date'])) {
            $where[] = "substr(happened_at, 1, 10) = ?";
            $params[] = (string)$filters['date'];
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int)self::db()->fetchColumn('SELECT COUNT(*) FROM activities' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = self::db()->fetchAll(
            'SELECT * FROM activities' . $whereSql . ' ORDER BY happened_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        return [
            'items' => array_map(static fn(array $row): self => new self($row), $rows),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public static function recentPublic(int $limit = 3): array
    {
        $rows = self::db()->fetchAll(
            "SELECT * FROM activities WHERE visibility = 'public' ORDER BY happened_at DESC, id DESC LIMIT {$limit}"
        );
        return array_map(static fn(array $row): self => new self($row), $rows);
    }

    public function metadata(): array
    {
        $data = json_decode((string)($this->metadata ?? ''), true);
        return is_array($data) ? $data : [];
    }

    public function save(): bool
    {
        $data = $this->attributes;
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);
            self::db()->update('activities', $data, 'id = :id', [':id' => $id]);
            return true;
        }
        $id = self::db()->insert('activities', $data);
        $this->attributes['id'] = $id;
        return true;
    }

    public function delete(): bool
    {
        if (empty($this->attributes['id'])) {
            return false;
        }
        return self::db()->delete('activities', 'id = ?', [(int)$this->attributes['id']]) > 0;
    }
}
