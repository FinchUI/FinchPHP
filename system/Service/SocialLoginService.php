<?php

/**
 * Finch\Service\SocialLoginService - 社会化登录门面（可降级）
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Database;
use Finch\Model\User;
use Finch\Model\UserSocialAccount;

final class SocialLoginService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string,mixed> $profile
     * @return array{ok:bool,user_id:int,message:string}
     */
    public function bindOrCreate(string $provider, string $providerUid, array $profile = []): array
    {
        $provider = trim($provider);
        $providerUid = trim($providerUid);

        if ($provider === '' || $providerUid === '') {
            return ['ok' => false, 'user_id' => 0, 'message' => 'provider/provider_uid required'];
        }

        $providerResult = ProviderRuntime::dispatch('social', $provider, 'bind', [
            'provider' => $provider,
            'provider_uid' => $providerUid,
            'profile' => $profile,
        ]);

        if (is_array($providerResult)) {
            return [
                'ok' => !isset($providerResult['ok']) || (bool) $providerResult['ok'],
                'user_id' => isset($providerResult['user_id']) && is_numeric($providerResult['user_id'])
                    ? (int) $providerResult['user_id']
                    : 0,
                'message' => isset($providerResult['message']) && is_scalar($providerResult['message'])
                    ? (string) $providerResult['message']
                    : 'social provider handled',
            ];
        }

        $existing = UserSocialAccount::findByProvider($provider, $providerUid);
        if (is_array($existing) && is_numeric($existing['user_id'] ?? null)) {
            return ['ok' => true, 'user_id' => (int) $existing['user_id'], 'message' => 'bound user found'];
        }

        $username = $this->nextUsername($provider);
        $email = $this->placeholderEmail($provider, $providerUid);
        $displayName = isset($profile['display_name']) && is_scalar($profile['display_name'])
            ? trim((string) $profile['display_name'])
            : $username;

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role_id' => 4,
            'status' => 'active',
            'is_admin' => 0,
        ]);

        UserSocialAccount::create([
            'user_id' => (int) $user->id,
            'provider' => $provider,
            'provider_uid' => $providerUid,
            'union_id' => isset($profile['union_id']) && is_scalar($profile['union_id']) ? (string) $profile['union_id'] : null,
            'profile' => json_encode($profile, JSON_UNESCAPED_UNICODE),
        ]);

        return ['ok' => true, 'user_id' => (int) $user->id, 'message' => 'user created and bound'];
    }

    private function nextUsername(string $provider): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]/', '_', strtolower($provider));
        $base = trim((string) $base, '_');
        if ($base === '') {
            $base = 'social';
        }

        $suffix = bin2hex(random_bytes(4));

        return $base . '_' . $suffix;
    }

    private function placeholderEmail(string $provider, string $providerUid): string
    {
        $local = preg_replace('/[^A-Za-z0-9_.-]/', '_', strtolower($provider . '_' . $providerUid));
        $local = trim((string) $local, '_');
        if ($local === '') {
            $local = 'social_user_' . bin2hex(random_bytes(4));
        }

        return $local . '@local.invalid';
    }
}
