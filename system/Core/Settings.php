<?php

/**
 * Finch\Core\Settings - 运行期设置容器（$fp->settings）
 *
 * 启动阶段一次性加载 system_setting 中 autoload=1 的项（见 PROJECT_PLAN §4.3）；
 * 非 autoload 项按需读取。值按 type 列做类型还原。
 */

declare(strict_types=1);

namespace Finch\Core;

final class Settings
{
    /** @var array<string, mixed> 已加载的 autoload 设置 */
    private array $items = [];

    private bool $loaded = false;

    public function __construct(private readonly Database $db)
    {
    }

    /** 加载 autoload=1 的系统设置 */
    public function load(): void
    {
        $rows = $this->db->table('system_setting')
            ->where('autoload', 1)
            ->get();

        foreach ($rows as $row) {
            $this->items[(string) $row['name']] = $this->cast($row['value'], (string) $row['type']);
        }

        $this->loaded = true;
    }

    /**
     * 读取设置项。autoload 项命中内存；否则查库。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $row = $this->db->table('system_setting')->where('name', $key)->first();
        if ($row === null) {
            return $default;
        }

        $value = $this->cast($row['value'], (string) $row['type']);
        if ((int) $row['autoload'] === 1) {
            $this->items[$key] = $value;
        }

        return $value;
    }

    /** 是否已加载 autoload 项 */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /** @return array<string, mixed> 已加载的 autoload 设置快照 */
    public function all(): array
    {
        return $this->items;
    }

    /** 更新内存中的 autoload 设置快照。 */
    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    /** 按 type 列还原值类型 */
    public function cast(mixed $value, string $type): mixed
    {
        $raw = (string) $value;

        return match ($type) {
            'int'   => (int) $raw,
            'bool'  => $raw === '1' || strtolower($raw) === 'true',
            'float' => (float) $raw,
            'json'  => json_decode($raw, true),
            default => $raw,
        };
    }
}
