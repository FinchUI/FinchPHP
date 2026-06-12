<?php

/**
 * Finch\Model\User - 用户/会员模型
 *
 * 对应 user 表（见 PROJECT_PLAN §4.4.2）。
 */

declare(strict_types=1);

namespace Finch\Model;

use Finch\Database\Query;

final class User extends BaseModel
{
    protected static string $table = 'user';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /** 按用户名查找 */
    public static function findByUsername(string $username): ?static
    {
        $row = self::query()->where('username', $username)->first();

        return $row === null ? null : self::newFromRow($row);
    }

    /** 关联角色 */
    public function role(): Query
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** 校验明文密码是否匹配 */
    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, (string) ($this->attributes['password'] ?? ''));
    }

    /** 判断用户是否具备指定能力。管理员或角色含 * 时放行。 */
    public function can(string $capability): bool
    {
        if ((int) ($this->attributes['is_admin'] ?? 0) === 1) {
            return true;
        }

        $roleId = $this->attributes['role_id'] ?? null;
        if (!is_numeric($roleId)) {
            return false;
        }

        $role = Role::find((int) $roleId);
        if (!$role instanceof Role) {
            return false;
        }

        $capabilities = $role->capabilities();
        $userCapabilities = $this->userCapabilities();

        if (in_array('!' . $capability, $userCapabilities, true) || in_array('!*', $userCapabilities, true)) {
            return false;
        }

        return in_array('*', $capabilities, true)
            || in_array($capability, $capabilities, true)
            || in_array('*', $userCapabilities, true)
            || in_array($capability, $userCapabilities, true);
    }

    /** @return list<string> */
    private function userCapabilities(): array
    {
        $raw = $this->attributes['capabilities'] ?? '[]';
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
