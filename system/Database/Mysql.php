<?php

/**
 * Finch\Database\Mysql - PDO MySQL / MariaDB 驱动
 *
 * 默认 InnoDB + utf8mb4（见 PROJECT_PLAN §4.3）。
 */

declare(strict_types=1);

namespace Finch\Database;

use PDO;

final class Mysql extends AbstractDriver
{
    public function init(): void
    {
        $host = (string) ($this->config['host'] ?? 'localhost');
        $port = (int) ($this->config['port'] ?? 3306);
        $database = (string) ($this->config['database'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $timeout = (int) ($this->config['timeout'] ?? 5);

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $options = $this->defaultOptions();
        $options[PDO::ATTR_TIMEOUT] = $timeout;

        $this->pdo = new PDO(
            $dsn,
            (string) ($this->config['username'] ?? ''),
            (string) ($this->config['password'] ?? ''),
            $options,
        );

        // 统一时区为 UTC，确保 gmdate() 存入的值与比较值含义一致
        $this->pdo->exec("SET time_zone = '+00:00'");
    }

    public function getVersion(): string
    {
        return (string) $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    public function getName(): string
    {
        return 'mysql';
    }
}
