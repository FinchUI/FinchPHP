<?php

/**
 * Finch\Database\Sqlite - PDO SQLite 驱动
 *
 * 初始化时自动执行默认 PRAGMA（见 PROJECT_PLAN §4.3）：
 *   WAL 日志、外键约束、NORMAL 同步、内存临时表、2MB 缓存。
 */

declare(strict_types=1);

namespace Finch\Database;

use PDO;

final class Sqlite extends AbstractDriver
{
    public function init(): void
    {
        $database = (string) ($this->config['database'] ?? '');

        // 允许 :memory: 用于测试；文件路径需确保目录存在
        if ($database !== ':memory:' && $database !== '') {
            $dir = dirname($database);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        $dsn = 'sqlite:' . $database;

        $this->pdo = new PDO($dsn, null, null, $this->defaultOptions());

        $this->applyPragmas();
    }

    /** 应用默认 PRAGMA 以平衡并发、安全与小内存主机性能 */
    private function applyPragmas(): void
    {
        $cacheSize = (int) ($this->config['cache_size'] ?? -2000);

        $pragmas = [
            'PRAGMA journal_mode=WAL;',
            'PRAGMA foreign_keys=ON;',
            'PRAGMA synchronous=NORMAL;',
            'PRAGMA temp_store=MEMORY;',
            'PRAGMA cache_size=' . $cacheSize . ';',
        ];

        foreach ($pragmas as $pragma) {
            $this->pdo()->exec($pragma);
        }
    }

    public function getVersion(): string
    {
        return (string) $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    public function getName(): string
    {
        return 'sqlite';
    }
}
