<?php

/**
 * Finch\Model\SmsCode - 短信验证码模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class SmsCode extends BaseModel
{
    protected static string $table = 'sms_code';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = false;

    /**
     * @param array<string,mixed> $data
     */
    public static function issue(array $data): int
    {
        $row = array_replace([
            'phone' => '',
            'code' => '',
            'scene' => 'login',
            'used' => 0,
            'ip' => null,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], $data);

        return (int) self::db()->table(static::$table)->insert($row);
    }

    public static function latestValid(string $phone, string $scene = 'login'): ?array
    {
        return self::query()
            ->where('phone', $phone)
            ->where('scene', $scene)
            ->where('used', 0)
            ->orderBy('id', 'DESC')
            ->first();
    }
}
