<?php

/**
 * Finch\Core\Cache - 运行期缓存管理器
 *
 * 基于 cache 表实现键值缓存与按组失效，供服务层做内容/设置变更后的缓存清理。
 */

declare(strict_types=1);

namespace Finch\Core;

use stdClass;

final class Cache
{
    public function __construct(
        private readonly Database $db,
        private readonly string $namespace = 'finch',
    ) {
    }

    public function get(string $key, mixed $default = null, string $group = 'default'): mixed
    {
        $cacheKey = $this->cacheKey($group, $key);
        $row = $this->db->table('cache')->where('key', $cacheKey)->first();
        if ($row === null) {
            return $default;
        }

        $expiresAt = (string) ($row['expired_at'] ?? '');
        if ($expiresAt === '' || strtotime($expiresAt . ' UTC') <= time()) {
            $this->db->table('cache')->where('key', $cacheKey)->delete();

            return $default;
        }

        $decoded = @unserialize((string) ($row['value'] ?? ''), ['allowed_classes' => false]);

        return $decoded === false && (string) ($row['value'] ?? '') !== 'b:0;'
            ? $default
            : $decoded;
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 300, string $group = 'default'): void
    {
        $cacheKey = $this->cacheKey($group, $key);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(1, $ttlSeconds));
        $payload = serialize($value);

        if ($this->db->table('cache')->where('key', $cacheKey)->exists()) {
            $this->db->table('cache')->where('key', $cacheKey)->update([
                'value' => $payload,
                'expired_at' => $expiresAt,
            ]);

            return;
        }

        $this->db->table('cache')->insert([
            'key' => $cacheKey,
            'value' => $payload,
            'expired_at' => $expiresAt,
        ]);
    }

    public function remember(string $key, int $ttlSeconds, callable $resolver, string $group = 'default'): mixed
    {
        $miss = new stdClass();
        $cached = $this->get($key, $miss, $group);
        if ($cached !== $miss) {
            return $cached;
        }

        $value = $resolver();
        $this->put($key, $value, $ttlSeconds, $group);

        return $value;
    }

    public function forget(string $key, string $group = 'default'): void
    {
        $this->db->table('cache')->where('key', $this->cacheKey($group, $key))->delete();
    }

    public function flushGroup(string $group): int
    {
        return $this->db->table('cache')
            ->where('key', 'LIKE', $this->groupPrefix($group) . '%')
            ->delete();
    }

    /** @param list<string> $groups */
    public function flushGroups(array $groups): int
    {
        $total = 0;
        foreach ($groups as $group) {
            $total += $this->flushGroup($group);
        }

        return $total;
    }

    public function clear(): int
    {
        return $this->db->table('cache')->delete();
    }

    public function pruneExpired(): int
    {
        return $this->db->table('cache')
            ->where('expired_at', '<=', gmdate('Y-m-d H:i:s'))
            ->delete();
    }

    private function cacheKey(string $group, string $key): string
    {
        return $this->groupPrefix($group) . sha1($key);
    }

    private function groupPrefix(string $group): string
    {
        return 'v1:' . $this->sanitize($this->namespace) . ':g:' . $this->sanitize($group) . ':';
    }

    private function sanitize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'default' : $value;
    }
}
