<?php

/**
 * Finch\Database\Query - SQL 构建器
 *
 * 链式生成 SELECT/INSERT/UPDATE/DELETE（见 PROJECT_PLAN §4.3）。
 * 所有值一律走预处理绑定，表名自动加前缀，禁止拼接用户输入。
 */

declare(strict_types=1);

namespace Finch\Database;

use InvalidArgumentException;

final class Query
{
    /** @var list<string> 选择的列 */
    private array $columns = ['*'];

    /** @var list<array{type:string,sql:string,boolean:string}> WHERE 条件片段 */
    private array $wheres = [];

    /** @var list<mixed> WHERE 绑定值（按出现顺序） */
    private array $bindings = [];

    /** @var list<string> JOIN 子句 */
    private array $joins = [];

    /** @var list<string> ORDER BY 片段 */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(
        private readonly Driver $driver,
        private readonly string $table,
    ) {
    }

    /**
     * 指定查询列。
     *
     * @param string ...$columns
     */
    public function select(string ...$columns): self
    {
        $this->columns = $columns === [] ? ['*'] : array_values($columns);

        return $this;
    }

    /**
     * 添加 WHERE 条件。支持 where('id', 1) 或 where('age', '>', 18)。
     */
    public function where(string $column, mixed $operator, mixed $value = null, string $boolean = 'AND'): self
    {
        // 两参形式：where('id', 1) 等价于 where('id', '=', 1)
        if (func_num_args() === 2 || ($value === null && func_num_args() === 2)) {
            $value = $operator;
            $operator = '=';
        }

        $this->assertOperator($operator);
        $this->wheres[] = [
            'type'    => 'basic',
            'sql'     => $this->quoteIdentifier($column) . ' ' . $operator . ' ?',
            'boolean' => $boolean,
        ];
        $this->bindings[] = $value;

        return $this;
    }

    /** OR WHERE 条件 */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, $operator, null, 'OR');
        }

        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * WHERE IN 条件。
     *
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if ($values === []) {
            // 空集合：构造恒假条件，避免语法错误
            $this->wheres[] = ['type' => 'raw', 'sql' => '1 = 0', 'boolean' => $boolean];

            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'type'    => 'in',
            'sql'     => $this->quoteIdentifier($column) . ' IN (' . $placeholders . ')',
            'boolean' => $boolean,
        ];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /** WHERE 列为 NULL */
    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type'    => 'null',
            'sql'     => $this->quoteIdentifier($column) . ' IS NULL',
            'boolean' => $boolean,
        ];

        return $this;
    }

    /** WHERE 列非 NULL */
    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type'    => 'notnull',
            'sql'     => $this->quoteIdentifier($column) . ' IS NOT NULL',
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * INNER/LEFT JOIN。第二张表也会自动加前缀。
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->assertOperator($operator);
        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            strtoupper($type),
            $this->prefixedTable($table),
            $this->quoteIdentifier($first),
            $operator,
            $this->quoteIdentifier($second),
        );

        return $this;
    }

    /** ORDER BY */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->quoteIdentifier($column) . ' ' . $direction;

        return $this;
    }

    /** LIMIT，可选 OFFSET */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = max(0, $limit);
        if ($offset !== null) {
            $this->offset = max(0, $offset);
        }

        return $this;
    }

    /** 偏移量 */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    // ---------------------------------------------------------------------
    // 终结方法
    // ---------------------------------------------------------------------

    /**
     * 取多行。
     *
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $sql = $this->compileSelect();

        return $this->driver->select($sql, $this->bindings);
    }

    /**
     * 取首行，无结果返回 null。
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $this->limit = 1;
        $sql = $this->compileSelect();

        return $this->driver->selectOne($sql, $this->bindings);
    }

    /** 计数 */
    public function count(): int
    {
        $this->columns = ['COUNT(*) AS aggregate'];
        $sql = $this->compileSelect();
        $row = $this->driver->selectOne($sql, $this->bindings);

        return (int) ($row['aggregate'] ?? 0);
    }

    /** 是否存在符合条件的记录 */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 分页查询。
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // 计数需独立绑定，先快照当前状态
        $countQuery = clone $this;
        $total = $countQuery->count();

        $this->limit($perPage, ($page - 1) * $perPage);
        $data = $this->get();

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * 插入一行，返回自增主键。
     *
     * @param array<string, mixed> $values
     */
    public function insert(array $values): string
    {
        if ($values === []) {
            throw new InvalidArgumentException('insert() 需要至少一个字段。');
        }

        $columns = array_keys($values);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $quotedColumns = implode(', ', array_map($this->quoteIdentifier(...), $columns));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->prefixedTable($this->table),
            $quotedColumns,
            $placeholders,
        );

        $this->driver->execute($sql, array_values($values));

        return $this->driver->getInsertId();
    }

    /**
     * 按当前 WHERE 条件更新，返回受影响行数。
     *
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        if ($values === []) {
            throw new InvalidArgumentException('update() 需要至少一个字段。');
        }

        $assignments = [];
        $setBindings = [];
        foreach ($values as $column => $value) {
            $assignments[] = $this->quoteIdentifier($column) . ' = ?';
            $setBindings[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->prefixedTable($this->table),
            implode(', ', $assignments),
            $this->compileWheres(),
        );

        return $this->driver->execute($sql, [...$setBindings, ...$this->bindings]);
    }

    /** 按当前 WHERE 条件删除，返回受影响行数 */
    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->prefixedTable($this->table),
            $this->compileWheres(),
        );

        return $this->driver->execute($sql, $this->bindings);
    }

    // ---------------------------------------------------------------------
    // 编译与辅助
    // ---------------------------------------------------------------------

    /** 编译完整 SELECT 语句 */
    private function compileSelect(): string
    {
        $columns = implode(', ', array_map(
            fn (string $col): string => $col === '*' || str_contains($col, '(')
                ? $col
                : $this->quoteIdentifier($col),
            $this->columns,
        ));

        $sql = 'SELECT ' . $columns . ' FROM ' . $this->prefixedTable($this->table);

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->compileWheres();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return $sql;
    }

    /** 编译 WHERE 子句（含 AND/OR 连接） */
    private function compileWheres(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $sql = '';
        foreach ($this->wheres as $i => $where) {
            $sql .= $i === 0 ? ' WHERE ' : ' ' . $where['boolean'] . ' ';
            $sql .= $where['sql'];
        }

        return $sql;
    }

    /** 给表名加前缀 */
    private function prefixedTable(string $table): string
    {
        $this->assertIdentifier($table);

        return $this->quoteIdentifier($this->driver->getPrefix() . $table);
    }

    /**
     * 引用标识符。支持 table.column 形式，分段引用。
     * 标识符仅允许字母/数字/下划线，从源头杜绝注入。
     */
    private function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier, 2);

            return $this->quoteSegment($parts[0]) . '.' . $this->quoteSegment($parts[1]);
        }

        return $this->quoteSegment($identifier);
    }

    /** 引用单段标识符 */
    private function quoteSegment(string $segment): string
    {
        $this->assertIdentifier($segment);

        return '`' . $segment . '`';
    }

    /** 校验标识符白名单 */
    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("非法标识符：{$identifier}");
        }
    }

    /** 校验运算符白名单 */
    private function assertOperator(string $operator): void
    {
        $allowed = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];
        if (!in_array(strtoupper($operator), $allowed, true)) {
            throw new InvalidArgumentException("非法运算符：{$operator}");
        }
    }
}
