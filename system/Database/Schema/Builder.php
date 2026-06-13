<?php

/**
 * Finch\Database\Schema\Builder - DDL 编译器
 *
 * 将 Blueprint 编译为 MySQL / SQLite 各自的建表与索引语句并执行
 * （见 PROJECT_PLAN §4.19.2 字段类型映射）。
 */

declare(strict_types=1);

namespace Finch\Database\Schema;

use Finch\Database\Driver;

final class Builder
{
    public function __construct(private readonly Driver $driver)
    {
    }

    /** 定义并创建一张表 */
    public function create(string $table, callable $definition): void
    {
        $blueprint = new Blueprint($table);
        $definition($blueprint);

        foreach ($this->compileCreate($blueprint) as $sql) {
            $this->driver->execute($sql);
        }
    }

    /** 删除表（IF EXISTS） */
    public function drop(string $table): void
    {
        $name = $this->prefixed($table);
        $this->driver->execute('DROP TABLE IF EXISTS ' . $this->quote($name));
    }

    /** 表是否存在 */
    public function hasTable(string $table): bool
    {
        $name = $this->prefixed($table);

        if ($this->driver->getName() === 'sqlite') {
            $row = $this->driver->selectOne(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$name],
            );

            return $row !== null;
        }

        // SHOW TABLES 不与 MySQL 原生预处理兼容，改用 INFORMATION_SCHEMA
        $row = $this->driver->selectOne(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$name],
        );

        return $row !== null;
    }

    /**
     * 编译建表语句（含独立索引语句）。
     *
     * @return list<string>
     */
    private function compileCreate(Blueprint $blueprint): array
    {
        $isSqlite = $this->driver->getName() === 'sqlite';
        $tableName = $this->prefixed($blueprint->table);

        $lines = [];
        foreach ($blueprint->getColumns() as $column) {
            $lines[] = '  ' . $this->compileColumn($column, $isSqlite);
        }

        // 复合主键（关联表）作为表级约束
        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'primary') {
                $cols = implode(', ', array_map($this->quote(...), $index['columns']));
                $lines[] = '  PRIMARY KEY (' . $cols . ')';
            }
        }

        $engine = $isSqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $statements = [
            sprintf("CREATE TABLE %s (\n%s\n)%s", $this->quote($tableName), implode(",\n", $lines), $engine),
        ];

        // 普通/唯一索引作为独立语句（两种数据库通用）
        foreach ($blueprint->getIndexes() as $index) {
            if ($index['type'] === 'primary') {
                continue;
            }
            $statements[] = $this->compileIndex($tableName, $index);
        }

        return $statements;
    }

    /** 编译单列定义 */
    private function compileColumn(Column $column, bool $isSqlite): string
    {
        // SQLite 自增主键必须是 INTEGER PRIMARY KEY AUTOINCREMENT
        if ($column->type === 'id') {
            if ($isSqlite) {
                return $this->quote($column->name) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
            }

            return $this->quote($column->name) . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        }

        $sql = $this->quote($column->name) . ' ' . $this->compileType($column, $isSqlite);

        if (!$isSqlite && $column->unsigned && $this->isNumeric($column->type)) {
            $sql .= ' UNSIGNED';
        }

        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault) {
            $sql .= ' DEFAULT ' . $this->compileDefault($column->default);
        }

        return $sql;
    }

    /** 抽象类型 → 具体数据库类型 */
    private function compileType(Column $column, bool $isSqlite): string
    {
        if ($isSqlite) {
            // SQLite 亲和类型：数值用 INTEGER，文本统一 TEXT
            return match ($column->type) {
                'bigInteger', 'integer', 'tinyInteger', 'boolean' => 'INTEGER',
                'string', 'text', 'mediumText', 'longText', 'json', 'timestamp' => 'TEXT',
                default => 'TEXT',
            };
        }

        return match ($column->type) {
            'bigInteger'  => 'BIGINT',
            'integer'     => 'INT',
            'tinyInteger' => 'TINYINT',
            'boolean'     => 'TINYINT(1)',
            'string'      => 'VARCHAR(' . ($column->length ?? 255) . ')',
            'text'        => 'TEXT',
            'mediumText'  => 'MEDIUMTEXT',
            'longText'    => 'LONGTEXT',
            'json'        => 'JSON',
            'timestamp'   => 'DATETIME',
            default       => 'TEXT',
        };
    }

    /** 默认值字面量 */
    private function compileDefault(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            is_null($value) => 'NULL',
            default => "'" . str_replace("'", "''", (string) $value) . "'",
        };
    }

    /** 编译独立索引语句 */
    private function compileIndex(string $tableName, array $index): string
    {
        $unique = $index['type'] === 'unique' ? 'UNIQUE ' : '';
        $cols = implode(', ', array_map($this->quote(...), $index['columns']));

        return sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $unique,
            $this->quote($index['name']),
            $this->quote($tableName),
            $cols,
        );
    }

    private function isNumeric(string $type): bool
    {
        return in_array($type, ['bigInteger', 'integer', 'tinyInteger'], true);
    }

    private function prefixed(string $table): string
    {
        return $this->driver->getPrefix() . $table;
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '', $identifier) . '`';
    }
}
