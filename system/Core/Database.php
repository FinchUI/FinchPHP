<?php

/**
 * Finch\Core\Database - 数据库抽象层（$fp->db）
 *
 * 根据配置选择并持有具体驱动（Mysql/Sqlite），对外提供统一的
 * 增删改查接口与查询构建器入口（见 PROJECT_PLAN §4.3）。
 */

declare(strict_types=1);

namespace Finch\Core;

use Finch\Database\Driver;
use Finch\Database\Mysql;
use Finch\Database\Query;
use Finch\Database\Sqlite;
use InvalidArgumentException;
use PDOStatement;

final class Database
{
    private Driver $driver;

    /**
     * @param array<string, mixed> $config 数据库配置段（来自 config.php['database']）
     */
    public function __construct(array $config)
    {
        $this->driver = $this->makeDriver($config);
    }

    /** 建立连接 */
    public function connect(): void
    {
        $this->driver->init();
    }

    /** 返回底层驱动 */
    public function driver(): Driver
    {
        return $this->driver;
    }

    /** 开启一个查询构建器，作用于指定表（不含前缀） */
    public function table(string $table): Query
    {
        return new Query($this->driver, $table);
    }

    /**
     * 执行原生 SQL（参数化），返回 PDOStatement。
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        return $this->driver->query($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->driver->select($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        return $this->driver->selectOne($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->driver->execute($sql, $params);
    }

    /** 表前缀 */
    public function prefix(): string
    {
        return $this->driver->getPrefix();
    }

    /** 在事务中执行回调，异常自动回滚 */
    public function transaction(callable $callback): mixed
    {
        $this->driver->beginTransaction();
        try {
            $result = $callback($this);
            $this->driver->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->driver->rollback();
            throw $e;
        }
    }

    /**
     * 根据配置创建驱动实例。
     *
     * @param array<string, mixed> $config
     */
    private function makeDriver(array $config): Driver
    {
        $name = strtolower((string) ($config['driver'] ?? 'mysql'));

        return match ($name) {
            'mysql', 'mariadb' => new Mysql($config),
            'sqlite'           => new Sqlite($config),
            default => throw new InvalidArgumentException("不支持的数据库驱动：{$name}"),
        };
    }
}
