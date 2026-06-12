<?php

/**
 * Finch\Model\PluginSetting - 插件配置模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class PluginSetting extends BaseModel
{
    protected static string $table = 'plugin_setting';

    protected static string $primaryKey = 'id';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = false;

    public static function value(string $plugin, string $name, ?string $default = null): ?string
    {
        $row = self::query()
            ->where('plugin', $plugin)
            ->where('name', $name)
            ->first();

        return $row === null ? $default : (string) $row['value'];
    }

    public static function put(string $plugin, string $name, mixed $value, string $type = 'string', bool $autoload = true): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $stored = self::stringify($value);

        $existing = self::query()
            ->where('plugin', $plugin)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            self::db()->table(static::$table)
                ->where('plugin', $plugin)
                ->where('name', $name)
                ->update([
                    'value' => $stored,
                    'type' => $type,
                    'autoload' => $autoload ? 1 : 0,
                    'updated_at' => $now,
                ]);

            return;
        }

        self::db()->table(static::$table)->insert([
            'plugin' => $plugin,
            'name' => $name,
            'value' => $stored,
            'type' => $type,
            'autoload' => $autoload ? 1 : 0,
            'updated_at' => $now,
        ]);
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
