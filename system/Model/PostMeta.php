<?php

/**
 * Finch\Model\PostMeta - 文章低频扩展字段模型
 *
 * 对应 post_meta 表（见 PROJECT_PLAN §4.4.3）。
 * 仅用于低频扩展字段，高频查询字段必须使用 post 表真实列。
 * meta_type 支持 string/int/float/bool/json/text，读取时自动类型转换。
 */

declare(strict_types=1);

namespace Finch\Model;

final class PostMeta extends BaseModel
{
    protected static string $table = 'post_meta';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /**
     * 获取文章指定 meta 值（自动类型转换）。
     */
    public static function get(int $postId, string $key, mixed $default = null): mixed
    {
        $row = self::query()
            ->where('post_id', $postId)
            ->where('meta_key', $key)
            ->first();

        if ($row === null) {
            return $default;
        }

        return self::castValue($row['meta_value'], $row['meta_type'] ?? 'string');
    }

    /**
     * 设置文章 meta（存在则更新，不存在则插入）。
     */
    public static function set(int $postId, string $key, mixed $value, string $type = 'string', bool $autoload = false): void
    {
        $serialized = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $now = gmdate('Y-m-d H:i:s');

        $existing = self::query()
            ->where('post_id', $postId)
            ->where('meta_key', $key)
            ->first();

        if ($existing !== null) {
            self::db()->table('post_meta')
                ->where('id', (int) $existing['id'])
                ->update([
                    'meta_value' => $serialized,
                    'meta_type' => $type,
                    'autoload' => $autoload ? 1 : 0,
                    'updated_at' => $now,
                ]);
        } else {
            self::db()->table('post_meta')->insert([
                'post_id' => $postId,
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
     * 删除文章指定 meta。
     */
    public static function remove(int $postId, string $key): bool
    {
        return self::db()->table('post_meta')
            ->where('post_id', $postId)
            ->where('meta_key', $key)
            ->delete() > 0;
    }

    /**
     * 获取文章所有 meta（键值对）。
     *
     * @return array<string, mixed>
     */
    public static function allForPost(int $postId): array
    {
        $rows = self::query()->where('post_id', $postId)->get();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['meta_key']] = self::castValue($row['meta_value'], $row['meta_type'] ?? 'string');
        }

        return $result;
    }

    /**
     * 删除文章所有 meta。
     */
    public static function deleteAllForPost(int $postId): int
    {
        return self::db()->table('post_meta')->where('post_id', $postId)->delete();
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
