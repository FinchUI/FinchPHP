<?php

/**
 * Finch\Service\SettingService - 运行期设置读写服务
 *
 * 负责后台系统设置写入、类型保持和当前请求缓存同步。
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Core\Database;
use Finch\Core\Settings;
use Finch\Model\SystemSetting;
use InvalidArgumentException;

final class SettingService
{
    public function __construct(
        private readonly Database $db,
        private readonly ?Settings $settings = null,
    ) {
    }

    /**
     * 读取指定设置项，按 type 转换。
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->settings?->get($key) ?? $this->get($key);
        }

        return $result;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->db->table('system_setting')->where('name', $key)->first();
        if ($row === null) {
            return $default;
        }

        return $this->settings?->cast($row['value'], (string) $row['type']) ?? (string) $row['value'];
    }

    /**
     * 更新后台允许编辑的系统设置。
     *
     * @param array<string, mixed> $input
     * @param list<string> $allowedKeys
     */
    public function updateSystem(array $input, array $allowedKeys): void
    {
        $touched = false;

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $meta = $this->meta($key);
            $value = $this->normalizeValue($input[$key], $meta['type']);
            SystemSetting::put($key, $value, $meta['type'], $meta['autoload']);

            if ($meta['autoload'] && $this->settings instanceof Settings) {
                $this->settings->set($key, $this->settings->cast(SystemSetting::value($key, ''), $meta['type']));
            }

            if ($key === 'site_timezone' && is_string($value) && in_array($value, timezone_identifiers_list(), true)) {
                date_default_timezone_set($value);
            }

            $touched = true;
        }

        if ($touched) {
            (new Cache($this->db))->flushGroups(['settings', 'theme', 'home', 'post', 'taxonomy', 'search']);
        }
    }

    /**
     * @return array{type:string,autoload:bool}
     */
    private function meta(string $key): array
    {
        $row = $this->db->table('system_setting')->where('name', $key)->first();
        if ($row === null) {
            throw new InvalidArgumentException("未知系统设置项：{$key}");
        }

        return [
            'type'     => (string) $row['type'],
            'autoload' => (int) $row['autoload'] === 1,
        ];
    }

    private function normalizeValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'   => (int) $value,
            'bool'  => $value === true || $value === '1' || $value === 1 || $value === 'true' || $value === 'on',
            'float' => (float) $value,
            'json'  => is_string($value) ? json_decode($value, true) : $value,
            default => is_array($value) ? implode(',', array_map('strval', $value)) : trim((string) $value),
        };
    }
}
