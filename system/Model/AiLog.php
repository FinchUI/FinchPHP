<?php

/**
 * Finch\Model\AiLog - AI 调用日志模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class AiLog extends BaseModel
{
    protected static string $table = 'ai_log';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = false;

    /**
     * @param array<string,mixed> $data
     */
    public static function write(array $data): int
    {
        $defaults = [
            'model_id' => null,
            'model_name' => null,
            'platform_name' => null,
            'user_id' => null,
            'caller_type' => null,
            'caller_name' => null,
            'scene' => null,
            'status' => 'success',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'duration_ms' => 0,
            'error_code' => null,
            'request_hash' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        $row = array_replace($defaults, $data);

        return (int) self::db()->table(static::$table)->insert($row);
    }
}
