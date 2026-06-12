<?php

/**
 * Finch\Core\Gate - 权限检查门面
 *
 * 统一封装角色能力校验。1.0 先调用 User::can()，保留后续用户级覆盖、
 * API Token abilities 与插件注册能力的扩展入口。
 */

declare(strict_types=1);

namespace Finch\Core;

use Finch\Model\User;

final class Gate
{
    /** @var array<string, string> 已注册能力 => 描述 */
    private array $capabilities = [];

    public function allows(?User $user, string $capability): bool
    {
        return $user instanceof User && $user->can($capability);
    }

    public function denies(?User $user, string $capability): bool
    {
        return !$this->allows($user, $capability);
    }

    public function register(string $capability, string $description = ''): void
    {
        $this->capabilities[$capability] = $description;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->capabilities;
    }
}
