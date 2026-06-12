<?php

/**
 * Finch\Service\AuthService - 登录认证服务
 *
 * 负责登录、退出、当前用户恢复与 remember_token 长期登录。
 * 控制器只协调请求和响应，认证细节集中在此服务。
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\App;
use Finch\Core\Database;
use Finch\Core\Request;
use Finch\Core\Session;
use Finch\Model\User;

final class AuthService
{
    private const SESSION_USER_ID = 'user_id';
    private const REMEMBER_COOKIE = 'fp_remember';
    private const MAX_REMEMBER_TOKENS = 5;

    public function __construct(
        private readonly Database $db,
        private readonly Session $session,
        private readonly Request $request,
        private readonly int $rememberDays = 30,
    ) {
    }

    /** 从 session 或 remember cookie 恢复当前用户。 */
    public function user(): ?User
    {
        $id = $this->session->get(self::SESSION_USER_ID);
        if (is_numeric($id)) {
            $user = User::find((int) $id);
            if ($this->isActive($user)) {
                return $user;
            }
            $this->session->forget(self::SESSION_USER_ID);
        }

        return $this->restoreFromRememberToken();
    }

    /** 尝试登录，成功返回用户，失败返回 null。 */
    public function attempt(string $username, string $password, bool $remember = false): ?User
    {
        $user = User::findByUsername($username);
        if (!$this->isActive($user)) {
            return null;
        }

        if (!$this->canAttempt($user)) {
            return null;
        }

        if (!$user->verifyPassword($password)) {
            $this->recordFailedLogin($user);

            return null;
        }

        $this->login($user, $remember);

        return $user;
    }

    /** 写入登录态。 */
    public function login(User $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_ID, (int) $user->id);

        $user->last_login_at = gmdate('Y-m-d H:i:s');
        $user->last_login_ip = $this->request->ip();
        $user->login_fail_count = 0;
        $user->locked_until = null;
        $user->save();

        if ($remember) {
            $this->issueRememberToken($user);
        }

        App::getInstance()->setCurrentUser($user);
    }

    /** 退出登录并撤销当前 remember token。 */
    public function logout(): void
    {
        $this->revokeCurrentRememberToken();
        $this->session->forget(self::SESSION_USER_ID);
        $this->session->regenerate();
        App::getInstance()->setCurrentUser(null);
    }

    private function restoreFromRememberToken(): ?User
    {
        $raw = $this->request->cookie(self::REMEMBER_COOKIE);
        if (!is_string($raw) || $raw === '' || !str_contains($raw, ':')) {
            return null;
        }

        [$id, $token] = explode(':', $raw, 2);
        if (!ctype_digit($id) || $token === '') {
            return null;
        }

        $row = $this->db->table('remember_token')
            ->where('id', (int) $id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($row === null || !$this->notExpired((string) $row['expires_at'])) {
            $this->forgetRememberCookie();

            return null;
        }

        $user = User::find((int) $row['user_id']);
        if (!$this->isActive($user)) {
            $this->forgetRememberCookie();

            return null;
        }

        $this->db->table('remember_token')
            ->where('id', (int) $row['id'])
            ->update(['last_used_at' => gmdate('Y-m-d H:i:s')]);
        $this->session->set(self::SESSION_USER_ID, (int) $user->id);

        return $user;
    }

    private function issueRememberToken(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(1, $this->rememberDays) * 86400);
        $id = $this->db->table('remember_token')->insert([
            'user_id'         => (int) $user->id,
            'token_hash'      => hash('sha256', $token),
            'user_agent_hash' => hash('sha256', $this->request->userAgent()),
            'ip'              => $this->request->ip(),
            'expires_at'      => $expiresAt,
            'last_used_at'    => null,
            'created_at'      => gmdate('Y-m-d H:i:s'),
        ]);

        setcookie(self::REMEMBER_COOKIE, $id . ':' . $token, [
            'expires'  => time() + max(1, $this->rememberDays) * 86400,
            'path'     => '/',
            'secure'   => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->pruneRememberTokens((int) $user->id);
    }

    private function pruneRememberTokens(int $userId): void
    {
        $rows = $this->db->table('remember_token')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();

        if (count($rows) <= self::MAX_REMEMBER_TOKENS) {
            return;
        }

        $deleteIds = array_map(static fn (array $row): int => (int) $row['id'], array_slice($rows, self::MAX_REMEMBER_TOKENS));
        $this->db->table('remember_token')->whereIn('id', $deleteIds)->delete();
    }

    private function revokeCurrentRememberToken(): void
    {
        $raw = $this->request->cookie(self::REMEMBER_COOKIE);
        if (is_string($raw) && str_contains($raw, ':')) {
            [$id, $token] = explode(':', $raw, 2);
            if (ctype_digit($id) && $token !== '') {
                $this->db->table('remember_token')
                    ->where('id', (int) $id)
                    ->where('token_hash', hash('sha256', $token))
                    ->delete();
            }
        }

        $this->forgetRememberCookie();
    }

    private function forgetRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function recordFailedLogin(User $user): void
    {
        $fails = (int) ($user->login_fail_count ?? 0) + 1;
        $user->login_fail_count = $fails;
        if ($fails >= 5) {
            $user->locked_until = gmdate('Y-m-d H:i:s', time() + 900);
        }
        $user->save();
    }

    private function canAttempt(User $user): bool
    {
        $lockedUntil = $user->locked_until;
        if (!is_string($lockedUntil) || $lockedUntil === '') {
            return true;
        }

        return strtotime($lockedUntil . ' UTC') <= time();
    }

    private function isActive(?User $user): bool
    {
        return $user instanceof User && (string) $user->status === 'active';
    }

    private function notExpired(string $utc): bool
    {
        return strtotime($utc . ' UTC') > time();
    }
}
