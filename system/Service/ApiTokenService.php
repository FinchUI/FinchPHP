<?php

/**
 * Finch\Service\ApiTokenService - API Token 签发与认证
 */

declare(strict_types=1);

namespace Finch\Service;

use DateTimeInterface;
use Finch\Core\Database;
use Finch\Core\Request;
use Finch\Model\ApiToken;
use Finch\Model\User;

final class ApiTokenService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 创建 API Token。plain_token 仅返回一次，数据库只存 hash。
     *
     * @param list<string> $abilities
     * @return array{plain_token:string,token:ApiToken}
     */
    public function create(User $user, string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): array
    {
        $plain = bin2hex(random_bytes(32));
        $token = ApiToken::create([
            'user_id'    => (int) $user->id,
            'name'       => $name,
            'token_hash' => ApiToken::hashToken($plain),
            'abilities'  => json_encode(array_values($abilities), JSON_UNESCAPED_UNICODE),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        return ['plain_token' => $plain, 'token' => $token];
    }

    public function authenticate(Request $request): ?ApiToken
    {
        $plain = $this->extractToken($request);
        if ($plain === null) {
            return null;
        }

        $token = ApiToken::findByPlainToken($plain);
        if (!$token instanceof ApiToken || $token->isExpired()) {
            return null;
        }

        $user = $token->user();
        if (!$user instanceof User || (string) $user->status !== 'active') {
            return null;
        }

        $token->touchLastUsed();

        return $token;
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (!is_string($header) || $header === '') {
            $header = $request->server('REDIRECT_HTTP_AUTHORIZATION');
        }

        if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) === 1) {
            return trim($matches[1]);
        }

        $token = $request->query('token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
