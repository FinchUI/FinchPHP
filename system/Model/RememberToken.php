<?php

/**
 * Finch\Model\RememberToken - 记住我长期登录 Token 模型
 *
 * 对应 remember_token 表（见 PROJECT_PLAN §4.4.2）。
 * 支持：SHA256 哈希存储、设备 UA 指纹、滑动续期、
 * 单用户最多 5 个有效 Token、登出按设备删除。
 */

declare(strict_types=1);

namespace Finch\Model;

final class RememberToken extends BaseModel
{
    protected static string $table = 'remember_token';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /** 明文 token 做 SHA256 哈希 */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /** 对 User-Agent 做 SHA256 截前 32 字符，用于设备识别 */
    public static function hashUserAgent(string $ua): string
    {
        return substr(hash('sha256', $ua), 0, 32);
    }

    /**
     * 按明文 token 查找有效记录。
     */
    public static function findByPlainToken(string $plainToken): ?self
    {
        $hash = self::hashToken($plainToken);
        $row = self::query()
            ->where('token_hash', $hash)
            ->where('expires_at', '>', gmdate('Y-m-d H:i:s'))
            ->first();

        return $row === null ? null : self::newFromRow($row);
    }

    /**
     * 为用户创建记住我 Token，自动清理超额的旧记录。
     *
     * @return string 明文 token（仅此一次返回，写入 Cookie）
     */
    public static function createForUser(int $userId, string $userAgent, string $ip, int $days = 30): string
    {
        $plain = bin2hex(random_bytes(32));
        $tokenHash = self::hashToken($plain);
        $uaHash = self::hashUserAgent($userAgent);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $days * 86400);
        $now = gmdate('Y-m-d H:i:s');

        // 单用户最多 5 个有效 token，超出删除最旧的
        $existing = self::db()->table('remember_token')
            ->where('user_id', $userId)
            ->where('expires_at', '>', $now)
            ->orderBy('last_used_at', 'ASC')
            ->get();

        if (count($existing) >= 5) {
            $deleteCount = count($existing) - 4; // 保留 4 个，新加 1 个 = 5
            for ($i = 0; $i < $deleteCount; $i++) {
                $id = (int) ($existing[$i]['id'] ?? 0);
                if ($id > 0) {
                    self::db()->table('remember_token')->where('id', $id)->delete();
                }
            }
        }

        self::db()->table('remember_token')->insert([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'user_agent_hash' => $uaHash,
            'ip' => $ip,
            'expires_at' => $expiresAt,
            'last_used_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $plain;
    }

    /**
     * 续期：剩余时间不足 1/3 时自动续期。
     */
    public function tryRenew(int $days = 30): void
    {
        $expiresAt = $this->attributes['expires_at'] ?? '';
        if ($expiresAt === '') {
            return;
        }

        $expiresTs = strtotime($expiresAt . ' UTC');
        $remaining = $expiresTs - time();
        $total = $days * 86400;

        if ($remaining < $total / 3) {
            $newExpires = gmdate('Y-m-d H:i:s', time() + $total);
            self::db()->table('remember_token')
                ->where('id', (int) $this->attributes['id'])
                ->update([
                    'expires_at' => $newExpires,
                    'last_used_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
        } else {
            // 仅更新 last_used_at
            $this->touchLastUsed();
        }
    }

    /**
     * 删除用户当前设备的 Token（登出时调用）。
     */
    public static function deleteForDevice(int $userId, string $userAgent): void
    {
        $uaHash = self::hashUserAgent($userAgent);
        self::db()->table('remember_token')
            ->where('user_id', $userId)
            ->where('user_agent_hash', $uaHash)
            ->delete();
    }

    /**
     * 删除用户所有 Token（强制全设备下线）。
     */
    public static function deleteAllForUser(int $userId): void
    {
        self::db()->table('remember_token')
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * 清理所有已过期的 Token（可由定时任务调用）。
     */
    public static function purgeExpired(): int
    {
        $now = gmdate('Y-m-d H:i:s');

        return self::db()->table('remember_token')
            ->where('expires_at', '<=', $now)
            ->delete();
    }

    private function touchLastUsed(): void
    {
        self::db()->table('remember_token')
            ->where('id', (int) $this->attributes['id'])
            ->update(['last_used_at' => gmdate('Y-m-d H:i:s')]);
    }
}
