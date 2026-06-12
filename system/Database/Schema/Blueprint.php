<?php

/**
 * Finch\Database\Schema\Blueprint - 表结构定义
 *
 * 以与数据库无关的方式声明表的列与索引（见 PROJECT_PLAN §4.19.2）。
 * 实际 DDL 由 Builder 按驱动编译。
 */

declare(strict_types=1);

namespace Finch\Database\Schema;

final class Blueprint
{
    /** @var list<Column> */
    private array $columns = [];

    /** @var list<array{type:string,columns:list<string>,name:string}> */
    private array $indexes = [];

    public function __construct(public readonly string $table)
    {
    }

    /** 自增主键 BIGINT id */
    public function id(string $name = 'id'): Column
    {
        $column = new Column($name, 'id');
        $column->autoIncrement = true;
        $column->primary = true;
        $column->unsigned = true;

        return $this->addColumn($column);
    }

    /** BIGINT 列 */
    public function bigInteger(string $name): Column
    {
        return $this->addColumn(new Column($name, 'bigInteger'));
    }

    /** INT 列 */
    public function integer(string $name): Column
    {
        return $this->addColumn(new Column($name, 'integer'));
    }

    /** TINYINT 列 */
    public function tinyInteger(string $name): Column
    {
        return $this->addColumn(new Column($name, 'tinyInteger'));
    }

    /** 布尔列（TINYINT(1)） */
    public function boolean(string $name): Column
    {
        return $this->addColumn(new Column($name, 'boolean'));
    }

    /** VARCHAR 列 */
    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn(new Column($name, 'string', $length));
    }

    /** TEXT 列 */
    public function text(string $name): Column
    {
        return $this->addColumn(new Column($name, 'text'));
    }

    /** MEDIUMTEXT 列 */
    public function mediumText(string $name): Column
    {
        return $this->addColumn(new Column($name, 'mediumText'));
    }

    /** LONGTEXT 列 */
    public function longText(string $name): Column
    {
        return $this->addColumn(new Column($name, 'longText'));
    }

    /** TIMESTAMP/DATETIME 列（统一存 UTC 字符串） */
    public function timestamp(string $name): Column
    {
        return $this->addColumn(new Column($name, 'timestamp'));
    }

    /** JSON 列（SQLite 退化为 TEXT） */
    public function json(string $name): Column
    {
        return $this->addColumn(new Column($name, 'json'));
    }

    /** 便捷：created_at / updated_at 两个时间列 */
    public function timestamps(): void
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
    }

    /**
     * 普通索引。
     *
     * @param string|list<string> $columns
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $this->addIndex('index', $columns, $name);
    }

    /**
     * 唯一索引。
     *
     * @param string|list<string> $columns
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->addIndex('unique', $columns, $name);
    }

    /**
     * 复合主键（用于关联表，如 post_category）。
     *
     * @param list<string> $columns
     */
    public function primary(array $columns): void
    {
        $this->addIndex('primary', $columns, 'PRIMARY');
    }

    /** @return list<Column> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return list<array{type:string,columns:list<string>,name:string}> */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    private function addColumn(Column $column): Column
    {
        $this->columns[] = $column;

        return $column;
    }

    /**
     * @param string|list<string> $columns
     */
    private function addIndex(string $type, string|array $columns, ?string $name): void
    {
        $cols = is_array($columns) ? array_values($columns) : [$columns];
        $name ??= $this->table . '_' . implode('_', $cols) . '_' . $type;

        $this->indexes[] = ['type' => $type, 'columns' => $cols, 'name' => $name];
    }
}
