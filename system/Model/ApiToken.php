<?php

/**
 * Finch\Model\ApiToken - API Token 模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class ApiToken extends BaseModel
{
    protected static string $table = 'api_token';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    public static function findByPlainToken(string $token): ?self
    {
        $row = self::query()->where('token_hash', self::hashToken($token))->first();

        return $row === null ? null : self::newFromRow($row);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return list<string> */
    public function abilities(): array
    {
        $raw = $this->attributes['abilities'] ?? '[]';
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->attributes['expires_at'] ?? null;
        if (!is_string($expiresAt) || $expiresAt === '') {
            return false;
        }

        return strtotime($expiresAt . ' UTC') <= time();
    }

    public function user(): ?User
    {
        $userId = $this->attributes['user_id'] ?? null;

        return is_numeric($userId) ? User::find((int) $userId) : null;
    }

    public function allows(string $ability): bool
    {
        $abilities = $this->abilities();

        foreach ($abilities as $entry) {
            if (str_starts_with($entry, '!') && $this->matchesAbility(substr($entry, 1), $ability)) {
                return false;
            }
        }

        foreach ($abilities as $entry) {
            if (!str_starts_with($entry, '!') && $this->matchesAbility($entry, $ability)) {
                return true;
            }
        }

        return false;
    }

    public function touchLastUsed(): void
    {
        self::db()->table(static::$table)
            ->where(static::$primaryKey, (int) $this->attributes[static::$primaryKey])
            ->update(['last_used_at' => gmdate('Y-m-d H:i:s')]);
    }

    private function matchesAbility(string $pattern, string $ability): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if ($pattern === $ability) {
            return true;
        }

        if (str_ends_with($pattern, ':*')) {
            return str_starts_with($ability, substr($pattern, 0, -1));
        }

        return false;
    }
}
