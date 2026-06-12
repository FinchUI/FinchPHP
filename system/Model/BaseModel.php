<?php

/**
 * Finch\Model\BaseModel - ORM 基类
 *
 * 通用 CRUD、字段映射、自动时间戳、软删除（见 PROJECT_PLAN §4.5）：
 *   - 自动时间戳：save() 时写入 created_at/updated_at，统一 UTC，不依赖数据库默认值
 *   - 软删除：含 deleted_at 的表默认过滤已删除记录，withTrashed() 显式包含
 *   - 关联：belongsTo / hasMany / hasOne / belongsToMany（1.0 提供查询入口）
 */

declare(strict_types=1);

namespace Finch\Model;

use Finch\App;
use Finch\Database\Query;

abstract class BaseModel
{
    /** 表名（不含前缀），子类必须覆盖 */
    protected static string $table = '';

    /** 主键列 */
    protected static string $primaryKey = 'id';

    /** 是否启用软删除（表需含 deleted_at 列） */
    protected static bool $softDeletes = false;

    /** 是否自动维护 created_at/updated_at */
    protected static bool $timestamps = true;

    /** @var array<string, mixed> 当前记录属性 */
    protected array $attributes = [];

    /** @var array<string, mixed> 原始属性（用于追踪变更） */
    protected array $original = [];

    /** 是否已持久化到数据库（读取或插入后为 true） */
    protected bool $persisted = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    // ---------------------------------------------------------------------
    // 属性访问
    // ---------------------------------------------------------------------

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

    /**
     * 批量赋值。
     *
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    // ---------------------------------------------------------------------
    // 查询入口
    // ---------------------------------------------------------------------

    /** 返回作用于本模型表的查询构建器（默认过滤软删除） */
    public static function query(): Query
    {
        $query = self::db()->table(static::$table);

        if (static::$softDeletes) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /** 返回包含软删除记录的查询构建器 */
    public static function withTrashed(): Query
    {
        return self::db()->table(static::$table);
    }

    /** 按主键查找，返回模型实例或 null */
    public static function find(int|string $id): ?static
    {
        $row = static::query()->where(static::$primaryKey, $id)->first();

        return $row === null ? null : static::newFromRow($row);
    }

    /**
     * 取全部记录（默认过滤软删除）。
     *
     * @return list<static>
     */
    public static function all(): array
    {
        $rows = static::query()->get();

        return array_map(static::newFromRow(...), $rows);
    }

    /**
     * 创建并持久化一条记录。
     *
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    // ---------------------------------------------------------------------
    // 持久化
    // ---------------------------------------------------------------------

    /** 保存（不存在则插入，存在则更新），返回是否成功 */
    public function save(): bool
    {
        $now = gmdate('Y-m-d H:i:s');

        if ($this->persisted) {
            if (static::$timestamps) {
                $this->attributes['updated_at'] = $now;
            }
            $id = $this->attributes[static::$primaryKey];
            $values = $this->attributes;
            unset($values[static::$primaryKey]);

            self::db()->table(static::$table)
                ->where(static::$primaryKey, $id)
                ->update($values);

            $this->original = $this->attributes;

            return true;
        }

        if (static::$timestamps) {
            $this->attributes['created_at'] ??= $now;
            $this->attributes['updated_at'] ??= $now;
        }

        $id = self::db()->table(static::$table)->insert($this->attributes);
        $this->attributes[static::$primaryKey] = is_numeric($id) ? (int) $id : $id;
        $this->original = $this->attributes;
        $this->persisted = true;

        return true;
    }

    /** 删除（软删除表写 deleted_at，否则物理删除），返回是否成功 */
    public function delete(): bool
    {
        if (!$this->persisted) {
            return false;
        }

        $id = $this->attributes[static::$primaryKey];

        if (static::$softDeletes) {
            $affected = self::db()->table(static::$table)
                ->where(static::$primaryKey, $id)
                ->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);

            return $affected > 0;
        }

        $affected = self::db()->table(static::$table)
            ->where(static::$primaryKey, $id)
            ->delete();

        return $affected > 0;
    }

    /** 记录是否已持久化到数据库 */
    public function exists(): bool
    {
        return $this->persisted;
    }

    // ---------------------------------------------------------------------
    // 关联（1.0 提供查询入口，单层 with 缓至后续完善）
    // ---------------------------------------------------------------------

    /** 归属关联：本表外键指向目标表主键 */
    protected function belongsTo(string $related, string $foreignKey): Query
    {
        /** @var class-string<BaseModel> $related */
        return $related::query()->where($related::primaryKeyName(), $this->attributes[$foreignKey] ?? null);
    }

    /** 一对多关联：目标表外键指向本表主键 */
    protected function hasMany(string $related, string $foreignKey): Query
    {
        /** @var class-string<BaseModel> $related */
        return $related::query()->where($foreignKey, $this->attributes[static::$primaryKey] ?? null);
    }

    /** 一对一关联 */
    protected function hasOne(string $related, string $foreignKey): Query
    {
        return $this->hasMany($related, $foreignKey);
    }

    /** 暴露主键列名供关联使用 */
    public static function primaryKeyName(): string
    {
        return static::$primaryKey;
    }

    /** 暴露表名 */
    public static function tableName(): string
    {
        return static::$table;
    }

    // ---------------------------------------------------------------------
    // 内部
    // ---------------------------------------------------------------------

    /**
     * 从数据库行构造模型实例。
     *
     * @param array<string, mixed> $row
     */
    protected static function newFromRow(array $row): static
    {
        $model = new static($row);
        $model->original = $row;
        $model->persisted = true;

        return $model;
    }

    /** 获取全局数据库实例 */
    protected static function db(): \Finch\Core\Database
    {
        return App::getInstance()->db;
    }
}
