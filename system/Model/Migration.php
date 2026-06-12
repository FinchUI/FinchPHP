<?php

/**
 * Finch\Model\Migration - 迁移记录模型包装
 */

declare(strict_types=1);

namespace Finch\Model;

final class Migration extends BaseModel
{
    protected static string $table = 'migration';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = false;

    /** @return list<array<string,mixed>> */
    public static function latest(int $limit = 20): array
    {
        return self::query()
            ->orderBy('id', 'DESC')
            ->limit(max(1, $limit))
            ->get();
    }
}
