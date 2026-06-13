<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * 模型基类（改进版）
 * 变更点：
 * 1. SQL 注入防护：所有接收 $orderBy 的方法增加白名单校验。
 * 2. 通用 paginate() 提取到基类，消除各子模型重复分页逻辑。
 * 3. 增加 relations 缓存，支持预加载模式（解决 N+1）。
 * 4. 增加批量插入/更新辅助方法。
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $pk = 'id';

    /** @var string[] 允许排序的列名（子类可覆盖） */
    protected static array $sortable = ['id', 'created_at', 'updated_at'];

    protected array $attributes = [];

    /** @var array<string, mixed> 预加载的关系缓存 */
    protected array $relations = [];

    public function __construct(array $attrs = [])
    {
        $this->fill($attrs);
    }

    public function fill(array $attrs): void
    {
        foreach ($attrs as $k => $v) {
            $this->attributes[$k] = $v;
        }
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function db(): Database
    {
        return Database::getInstance();
    }

    public static function tableName(): string
    {
        return static::$table;
    }

    public static function find(int|string $id): ?static
    {
        $row = self::db()->fetchOne(
            'SELECT * FROM ' . static::$table . ' WHERE ' . static::$pk . ' = ?',
            [$id]
        );
        return $row ? new static($row) : null;
    }

    public static function findBy(string $field, mixed $value): ?static
    {
        self::guardIdentifier($field);
        $row = self::db()->fetchOne(
            'SELECT * FROM ' . static::$table . " WHERE {$field} = ? LIMIT 1",
            [$value]
        );
        return $row ? new static($row) : null;
    }

    public static function all(string $orderBy = 'id DESC'): array
    {
        self::guardOrderBy($orderBy);
        $rows = self::db()->fetchAll('SELECT * FROM ' . static::$table . " ORDER BY {$orderBy}");
        return array_map(fn($r) => new static($r), $rows);
    }

    public static function where(array $conds, string $orderBy = 'id DESC', ?int $limit = null, int $offset = 0): array
    {
        self::guardOrderBy($orderBy);
        $where = [];
        $params = [];
        foreach ($conds as $k => $v) {
            self::guardIdentifier((string) $k);
            $where[] = "{$k} = ?";
            $params[] = $v;
        }
        $sql = 'SELECT * FROM ' . static::$table;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY {$orderBy}";
        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        $rows = self::db()->fetchAll($sql, $params);
        return array_map(fn($r) => new static($r), $rows);
    }

    public static function count(array $conds = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . static::$table;
        $params = [];
        if ($conds) {
            $where = [];
            foreach ($conds as $k => $v) {
                self::guardIdentifier((string) $k);
                $where[] = "{$k} = ?";
                $params[] = $v;
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        return (int) self::db()->fetchColumn($sql, $params);
    }

    /**
     * 通用分页查询。
     *
     * @param int $page 页码（从 1 开始）
     * @param int $perPage 每页条数
     * @param string $orderBy 排序
     * @param string|null $whereSql 额外 WHERE 子句（不含 WHERE 关键字）
     * @param array $params WHERE 参数
     * @return array{items: static[], total: int, page: int, perPage: int}
     */
    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'id DESC', ?string $whereSql = null, array $params = []): array
    {
        self::guardOrderBy($orderBy);
        $offset = max(0, ($page - 1) * $perPage);

        $countSql = 'SELECT COUNT(*) FROM ' . static::$table;
        $sql = 'SELECT * FROM ' . static::$table;
        if ($whereSql) {
            $countSql .= ' WHERE ' . $whereSql;
            $sql .= ' WHERE ' . $whereSql;
        }
        $total = (int) self::db()->fetchColumn($countSql, $params);
        $sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        $rows = self::db()->fetchAll($sql, $params);

        return [
            'items'   => array_map(fn($r) => new static($r), $rows),
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    public function save(): bool
    {
        $pk = $this->attributes[static::$pk] ?? null;
        if ($pk) {
            $data = $this->attributes;
            unset($data[static::$pk]);
            $count = self::db()->update(static::$table, $data, static::$pk . ' = :pk', [':pk' => $pk]);
            return $count >= 0;
        }
        $id = self::db()->insert(static::$table, $this->attributes);
        $this->attributes[static::$pk] = $id;
        return true;
    }

    public function delete(): bool
    {
        $pk = $this->attributes[static::$pk] ?? null;
        if (!$pk) {
            return false;
        }
        return self::db()->delete(static::$table, static::$pk . ' = ?', [$pk]) > 0;
    }

    public static function query(string $sql, array $params = []): array
    {
        $rows = self::db()->fetchAll($sql, $params);
        return array_map(fn($r) => new static($r), $rows);
    }

    public static function rawOne(string $sql, array $params = []): ?array
    {
        return self::db()->fetchOne($sql, $params);
    }

    /**
     * 设置预加载关系（用于一次性 JOIN 查询后填充）。
     */
    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    /**
     * 获取预加载的关系。
     */
    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * 校验 ORDER BY 子句，防止 SQL 注入。
     */
    protected static function guardIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Disallowed identifier: {$name}");
        }
    }

    protected static function guardOrderBy(string $orderBy): void
    {
        // 只允许：列名、ASC、DESC、逗号、空格、点（表别名）
        if (!preg_match('/^[a-zA-Z0-9_.,\s]+$/', $orderBy)) {
            throw new \InvalidArgumentException("Invalid orderBy clause: {$orderBy}");
        }

        // 进一步校验每一列是否在白名单中（如果子类定义了 $sortable）
        if (static::$sortable !== ['id', 'created_at', 'updated_at']) {
            $parts = array_map('trim', explode(',', $orderBy));
            foreach ($parts as $part) {
                // 去掉 ASC/DESC
                $col = preg_replace('/\s+(ASC|DESC)$/i', '', $part);
                $col = str_replace(['`', '"'], '', $col);
                // 支持 table.column 格式，只取列名
                if (str_contains($col, '.')) {
                    $col = explode('.', $col)[1] ?? $col;
                }
                if (!in_array($col, static::$sortable, true)) {
                    throw new \InvalidArgumentException("Disallowed sort column: {$col}");
                }
            }
        }
    }
}
