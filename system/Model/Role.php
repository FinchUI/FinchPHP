<?php

/**
 * Finch\Model\Role - 角色模型
 *
 * 对应 role 表（见 PROJECT_PLAN §4.4.2）。capabilities 以 JSON 存储。
 */

declare(strict_types=1);

namespace Finch\Model;

final class Role extends BaseModel
{
    protected static string $table = 'role';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /**
     * 解析该角色的能力列表。
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        $raw = $this->attributes['capabilities'] ?? '[]';
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
