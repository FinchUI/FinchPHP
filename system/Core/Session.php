<?php

/**
 * Finch\Core\Session - 会话与 CSRF 封装
 *
 * 统一管理登录态、Cookie 参数和 CSRF 令牌。1.0 先基于 PHP 原生 session，
 * session 表作为后续数据库会话存储预留。
 */

declare(strict_types=1);

namespace Finch\Core;

final class Session
{
    private bool $started = false;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config = [],
        private readonly ?Request $request = null,
    ) {
    }

    /** 启动会话。 */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        $lifetime = (int) ($this->config['lifetime'] ?? 0);
        $secure = (bool) ($this->config['secure'] ?? $this->request?->isSecure() ?? false);
        $httpOnly = (bool) ($this->config['httponly'] ?? true);
        $sameSite = (string) ($this->config['samesite'] ?? 'Lax');
        $name = (string) ($this->config['name'] ?? 'finch_session');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax',
        ]);
        session_start();
        $this->started = true;
    }

    /** 读取 session 值。 */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    /** 写入 session 值。 */
    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /** 删除 session 值。 */
    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /** 判断 session 值是否存在。 */
    public function has(string $key): bool
    {
        $this->start();

        return isset($_SESSION[$key]);
    }

    /** 重新生成 session id，登录成功时调用。 */
    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    /** 销毁当前会话。 */
    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        $this->started = false;
    }

    /** 获取或生成 CSRF 令牌。 */
    public function csrfToken(): string
    {
        $token = $this->get('_csrf_token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        $this->set('_csrf_token', $token);

        return $token;
    }

    /** 校验 CSRF 令牌。 */
    public function verifyCsrfToken(?string $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }
}
