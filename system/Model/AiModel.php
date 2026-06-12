<?php

/**
 * Finch\Model\AiModel - AI 模型配置
 */

declare(strict_types=1);

namespace Finch\Model;

final class AiModel extends BaseModel
{
    protected static string $table = 'ai_model';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /** @return list<array<string,mixed>> */
    public static function activeList(): array
    {
        return self::query()
            ->where('status', 'active')
            ->orderBy('is_default', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();
    }

    /** @return array<string,mixed>|null */
    public static function defaultRow(): ?array
    {
        $row = self::query()
            ->where('status', 'active')
            ->where('is_default', 1)
            ->orderBy('id', 'DESC')
            ->first();

        if ($row !== null) {
            return $row;
        }

        return self::query()
            ->where('status', 'active')
            ->orderBy('id', 'DESC')
            ->first();
    }
}
