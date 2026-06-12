<?php

/**
 * Finch\Database\Driver - 数据库驱动接口
 *
 * 所有驱动必须实现的方法（见 PROJECT_PLAN §4.3）。
 * 业务 SQL 一律通过预处理与 Query Builder 生成，禁止拼接用户输入。
 */

declare(strict_types=1);

namespace Finch\Database;

use PDO;
use PDOStatement;

interface Driver
{
    /** 建立连接并完成驱动初始化（如 SQLite 的 PRAGMA） */
    public function init(): void;

    /** 返回底层 PDO 实例 */
    public function pdo(): PDO;

    /**
     * 执行一条带参数的 SQL，返回 PDOStatement。
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement;

    /**
     * 查询多行。
     *
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array;

    /**
     * 查询单行，无结果返回 null。
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array;

    /**
     * 执行写操作（INSERT/UPDATE/DELETE），返回受影响行数。
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int;

    /** 最近一次 INSERT 的自增主键 */
    public function getInsertId(): string;

    /** 开启事务 */
    public function beginTransaction(): void;

    /** 提交事务 */
    public function commit(): void;

    /** 回滚事务 */
    public function rollback(): void;

    /** 是否处于事务中 */
    public function inTransaction(): bool;

    /** 表前缀 */
    public function getPrefix(): string;

    /** 数据库服务器版本 */
    public function getVersion(): string;

    /** 驱动标识：mysql / sqlite */
    public function getName(): string;
}
