<?php

/**
 * Finch\Model\UserSocialAccount - 第三方账号绑定模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class UserSocialAccount extends BaseModel
{
    protected static string $table = 'user_social_account';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /** @return array<string,mixed>|null */
    public static function findByProvider(string $provider, string $providerUid): ?array
    {
        return self::query()
            ->where('provider', $provider)
            ->where('provider_uid', $providerUid)
            ->first();
    }
}
