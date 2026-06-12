<?php

/**
 * Finch\Database\Migrator - 迁移执行器
 *
 * 扫描 Migrations/ 目录，按文件名（时间戳前缀）顺序执行未运行的迁移，
 * 并在 migration 表中记录批次（见 PROJECT_PLAN §4.3）。
 *
 * 迁移文件命名：{timestamp}_{snake_case}.php，返回一个 Migration 实例。
 */

declare(strict_types=1);

namespace Finch\Database;

use Finch\Core\Database;
use Finch\Database\Schema\Builder;
use RuntimeException;

final class Migrator
{
    private readonly Builder $schema;

    /** 核心迁移的固定 owner 标识（区别于插件/模块迁移） */
    private const OWNER_TYPE = 'core';
    private const OWNER_ID = 'system';

    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsPath,
    ) {
        $this->schema = new Builder($this->db->driver());
    }

    /**
     * 执行所有未运行的迁移。
     *
     * @return list<string> 本次执行的迁移名
     */
    public function run(): array
    {
        $this->ensureMigrationTable();

        $executed = $this->executedMigrations();
        $files = $this->discoverMigrations();
        $batch = $this->nextBatch();

        $applied = [];
        foreach ($files as $name => $file) {
            if (in_array($name, $executed, true)) {
                continue;
            }

            $migration = $this->load($file);
            $migration->up($this->schema);
            $this->record($name, $batch);
            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * 回滚最近一个批次。
     *
     * @return list<string> 本次回滚的迁移名
     */
    public function rollback(): array
    {
        $this->ensureMigrationTable();

        $prefix = $this->db->prefix();
        $lastBatch = $this->db->selectOne(
            "SELECT MAX(batch) AS batch FROM `{$prefix}migration` WHERE owner_type = ? AND owner_id = ?",
            [self::OWNER_TYPE, self::OWNER_ID],
        );
        $batch = (int) ($lastBatch['batch'] ?? 0);

        if ($batch === 0) {
            return [];
        }

        $rows = $this->db->table('migration')
            ->where('owner_type', self::OWNER_TYPE)
            ->where('owner_id', self::OWNER_ID)
            ->where('batch', $batch)
            ->orderBy('version', 'DESC')
            ->get();

        $files = $this->discoverMigrations();
        $rolledBack = [];

        foreach ($rows as $row) {
            $name = (string) $row['version'];
            if (!isset($files[$name])) {
                continue;
            }
            $migration = $this->load($files[$name]);
            $migration->down($this->schema);
            $this->db->table('migration')
                ->where('owner_type', self::OWNER_TYPE)
                ->where('owner_id', self::OWNER_ID)
                ->where('version', $name)
                ->delete();
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /** 是否已有任意迁移记录（用于判断是否首次安装） */
    public function hasRun(): bool
    {
        $this->ensureMigrationTable();

        return $this->db->table('migration')->exists();
    }

    /** 创建 migration 追踪表（若不存在），结构对齐 PROJECT_PLAN §4.4.3 */
    private function ensureMigrationTable(): void
    {
        if ($this->schema->hasTable('migration')) {
            return;
        }

        $this->schema->create('migration', function (\Finch\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 20)->default('core'); // core / plugin / module
            $table->string('owner_id', 100)->default('system'); // 插件/模块 ID，core 时为 system
            $table->string('version', 100);                     // 迁移文件名（时间戳前缀）
            $table->string('name', 255);                        // 迁移描述
            $table->integer('batch');
            $table->timestamp('executed_at');
            $table->unique(['owner_type', 'owner_id', 'version']);
            $table->index('batch');
        });
    }

    /** @return list<string> 已执行的核心迁移 version */
    private function executedMigrations(): array
    {
        $rows = $this->db->table('migration')
            ->select('version')
            ->where('owner_type', self::OWNER_TYPE)
            ->where('owner_id', self::OWNER_ID)
            ->get();

        return array_map(static fn (array $row): string => (string) $row['version'], $rows);
    }

    /** 计算下一个批次号 */
    private function nextBatch(): int
    {
        $prefix = $this->db->prefix();
        $row = $this->db->selectOne(
            "SELECT MAX(batch) AS batch FROM `{$prefix}migration` WHERE owner_type = ? AND owner_id = ?",
            [self::OWNER_TYPE, self::OWNER_ID],
        );

        return (int) ($row['batch'] ?? 0) + 1;
    }

    /** 记录一条迁移执行 */
    private function record(string $name, int $batch): void
    {
        $this->db->table('migration')->insert([
            'owner_type'  => self::OWNER_TYPE,
            'owner_id'    => self::OWNER_ID,
            'version'     => $name,
            'name'        => $name,
            'batch'       => $batch,
            'executed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 扫描迁移目录，返回 [迁移名 => 文件绝对路径]，按文件名升序。
     *
     * @return array<string, string>
     */
    private function discoverMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);

        $result = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            $result[$name] = $file;
        }

        return $result;
    }

    /** 加载迁移文件并返回 Migration 实例 */
    private function load(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new RuntimeException("迁移文件未返回 Migration 实例：{$file}");
        }

        return $migration;
    }
}
