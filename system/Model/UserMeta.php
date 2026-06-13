<?php

/**
 * Finch\Model\UserMeta - 用户低频扩展字段模型
 *
 * 对应 user_meta 表（见 PROJECT_PLAN §4.4.3）。
 * 仅用于低频扩展字段（如偏好设置、个人简介补充等），
 * 高频字段必须在 user 表真实列中。
 * meta_type 支持 string/int/float/bool/json/text，读取时自动类型转换。
 */

declare(strict_types=1);

namespace Finch\Model;

final class UserMeta extends BaseModel
{
    protected static string $table = 'user_meta';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /**
     * 获取用户指定 meta 值（自动类型转换）。
     */
    public static function get(int $userId, string $key, mixed $default = null): mixed
    {
        $row = self::query()
            ->where('user_id', $userId)
            ->where('meta_key', $key)
            ->first();

        if ($row === null) {
            return $default;
        }

        return self::castValue($row['meta_value'], $row['meta_type'] ?? 'string');
    }

    /**
     * 设置用户 meta（存在则更新，不存在则插入）。
     */
    public static function set(int $userId, string $key, mixed $value, string $type = 'string', bool $autoload = false): void
    {
        $serialized = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $now = gmdate('Y-m-d H:i:s');

        $existing = self::query()
            ->where('user_id', $userId)
            ->where('meta_key', $key)
            ->first();

        if ($existing !== null) {
            self::db()->table('user_meta')
                ->where('id', (int) $existing['id'])
                ->update([
                    'meta_value' => $serialized,
                    'meta_type' => $type,
                    'autoload' => $autoload ? 1 : 0,
                    'updated_at' => $now,
                ]);
        } else {
            self::db()->table('user_meta')->insert([
                'user_id' => $userId,
                'meta_key' => $key,
                'meta_value' => $serialized,
                'meta_type' => $type,
                'autoload' => $autoload ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 删除用户指定 meta。
     */
    public static function remove(int $userId, string $key): bool
    {
        return self::db()->table('user_meta')
            ->where('user_id', $userId)
            ->where('meta_key', $key)
            ->delete() > 0;
    }

    /**
     * 获取用户所有 meta（键值对）。
     *
     * @return array<string, mixed>
     */
    public static function allForUser(int $userId): array
    {
        $rows = self::query()->where('user_id', $userId)->get();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['meta_key']] = self::castValue($row['meta_value'], $row['meta_type'] ?? 'string');
        }

        return $result;
    }

    /**
     * 删除用户所有 meta。
     */
    public static function deleteAllForUser(int $userId): int
    {
        return self::db()->table('user_meta')->where('user_id', $userId)->delete();
    }

    /**
     * 按 meta_type 做类型转换。
     */
    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }
}
