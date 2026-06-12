<?php

/**
 * Finch\Model\SystemSetting - 系统运行期配置模型
 *
 * 对应 system_setting 表（见 PROJECT_PLAN §4.4.3 与配置项总表）。
 * 字段：id, name, value, type, autoload, updated_at（无 created_at）。
 */

declare(strict_types=1);

namespace Finch\Model;

final class SystemSetting extends BaseModel
{
    protected static string $table = 'system_setting';

    protected static string $primaryKey = 'id';

    protected static bool $softDeletes = false;

    /** 该表仅有 updated_at，不启用自动 created_at；时间戳手动维护 */
    protected static bool $timestamps = false;

    /**
     * 读取某项的值（不做类型还原，原样返回字符串）。
     */
    public static function value(string $name, ?string $default = null): ?string
    {
        $row = self::query()->where('name', $name)->first();

        return $row === null ? $default : (string) $row['value'];
    }

    /**
     * 写入/更新一项设置（存在则更新，否则插入）。
     */
    public static function put(string $name, mixed $value, string $type = 'string', bool $autoload = true): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $stored = self::stringify($value);

        $existing = self::query()->where('name', $name)->first();
        if ($existing !== null) {
            self::db()->table(static::$table)
                ->where('name', $name)
                ->update([
                    'value'      => $stored,
                    'type'       => $type,
                    'autoload'   => $autoload ? 1 : 0,
                    'updated_at' => $now,
                ]);

            return;
        }

        self::db()->table(static::$table)->insert([
            'name'       => $name,
            'value'      => $stored,
            'type'       => $type,
            'autoload'   => $autoload ? 1 : 0,
            'updated_at' => $now,
        ]);
    }

    /** 将值序列化为存储字符串 */
    private static function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
