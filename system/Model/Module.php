<?php

/**
 * Finch\Model\Module - 官方模块模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class Module extends BaseModel
{
    protected static string $table = 'module';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /** @return list<array<string,mixed>> */
    public static function activeList(): array
    {
        return self::query()
            ->where('is_active', 1)
            ->orderBy('position', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }
}
