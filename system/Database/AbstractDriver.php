<?php

/**
 * Finch\Database\AbstractDriver - PDO 驱动共享基类
 *
 * 封装 query/select/execute/事务等与具体数据库无关的逻辑，
 * 由 Mysql / Sqlite 复用（见 PROJECT_PLAN §4.3）。
 */

declare(strict_types=1);

namespace Finch\Database;

use PDO;
use PDOStatement;
use RuntimeException;

abstract class AbstractDriver implements Driver
{
    protected ?PDO $pdo = null;

    /**
     * @param array<string, mixed> $config 数据库配置段
     */
    public function __construct(protected array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            throw new RuntimeException('数据库尚未连接，请先调用 init()。');
        }

        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function getInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo()->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo()->inTransaction();
    }

    public function getPrefix(): string
    {
        return (string) ($this->config['prefix'] ?? '');
    }

    /** PDO 默认选项：异常模式、关闭模拟预处理、关联数组取值 */
    protected function defaultOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }
}
