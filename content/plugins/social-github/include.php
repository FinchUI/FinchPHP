<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_social_github_bind', static function (mixed $value, array $payload = []): mixed {
        if ($value !== null) {
            return $value;
        }

        $providerUid = trim((string) ($payload['provider_uid'] ?? ''));
        if ($providerUid === '') {
            return ['ok' => false, 'user_id' => 0, 'message' => 'github provider_uid required'];
        }

        $existing = \Finch\Model\UserSocialAccount::findByProvider('github', $providerUid);
        if (is_array($existing) && is_numeric($existing['user_id'] ?? null)) {
            return ['ok' => true, 'user_id' => (int) $existing['user_id'], 'message' => 'github bound user found'];
        }

        $profile = is_array($payload['profile'] ?? null) ? $payload['profile'] : [];

        try {
            $username = 'github_' . bin2hex(random_bytes(4));
            $displayName = isset($profile['display_name']) && is_scalar($profile['display_name'])
                ? trim((string) $profile['display_name'])
                : $username;
            $local = preg_replace('/[^A-Za-z0-9_.-]/', '_', strtolower('github_' . $providerUid));
            $local = is_string($local) ? trim($local, '_') : '';
            if ($local === '') {
                $local = 'github_' . bin2hex(random_bytes(4));
            }
            $email = $local . '@local.invalid';

            $user = \Finch\Model\User::create([
                'username' => $username,
                'email' => $email,
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'display_name' => $displayName,
                'role_id' => 4,
                'status' => 'active',
                'is_admin' => 0,
            ]);

            \Finch\Model\UserSocialAccount::create([
                'user_id' => (int) $user->id,
                'provider' => 'github',
                'provider_uid' => $providerUid,
                'union_id' => isset($profile['union_id']) && is_scalar($profile['union_id']) ? (string) $profile['union_id'] : null,
                'profile' => json_encode($profile, JSON_UNESCAPED_UNICODE),
            ]);

            return ['ok' => true, 'user_id' => (int) $user->id, 'message' => 'github user created and bound'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'user_id' => 0, 'message' => 'github bind failed: ' . $e->getMessage()];
        }
    });
};
