<?php

/**
 * Finch\Core\Request - HTTP 请求封装
 *
 * 安全获取 GET/POST/SERVER 参数（见 PROJECT_PLAN §4.1 目录说明）。
 * 业务代码（Service/Model）不得直接访问 $_GET/$_POST，统一经此类。
 */

declare(strict_types=1);

namespace Finch\Core;

final class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $post;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $cookies;

    /** @var array<string, mixed> */
    private array $files;

    public function __construct(
        ?array $query = null,
        ?array $post = null,
        ?array $server = null,
        ?array $cookies = null,
        ?array $files = null,
    ) {
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->server = $server ?? $_SERVER;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->files = $files ?? $_FILES;
    }

    /** 从全局超全局变量创建请求实例 */
    public static function capture(): self
    {
        return new self();
    }

    /** 读取 GET 参数 */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** 读取 POST 参数 */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /** 优先 POST，其次 GET */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** 读取 Cookie */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /** 读取 SERVER 项 */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /** 读取请求头。 */
    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (array_key_exists($key, $this->server)) {
            return $this->server[$key];
        }

        $contentKey = strtoupper(str_replace('-', '_', $name));
        if (in_array($contentKey, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) && array_key_exists($contentKey, $this->server)) {
            return $this->server[$contentKey];
        }

        return $default;
    }

    /** 所有输入（POST 优先覆盖 GET），供验证器使用。 */
    public function all(): array
    {
        return array_replace($this->query, $this->post);
    }

    /** 读取上传文件（单文件）。 */
    public function file(string $key, mixed $default = null): mixed
    {
        return $this->files[$key] ?? $default;
    }

    /** 读取全部上传文件数组。 */
    public function files(): array
    {
        return $this->files;
    }

    /** 请求方法（大写），如 GET/POST */
    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    /** 请求路径（不含查询串），始终以 / 开头 */
    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return '/' . ltrim(rawurldecode($path), '/');
    }

    /** 是否 HTTPS 请求 */
    public function isSecure(): bool
    {
        $https = (string) ($this->server['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return (string) ($this->server['SERVER_PORT'] ?? '') === '443';
    }

    /** 客户端 IP（信任直连 REMOTE_ADDR，代理头不默认信任） */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** User-Agent 字符串 */
    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    /** 是否 AJAX 请求（X-Requested-With） */
    public function isAjax(): bool
    {
        return strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** 是否期望 JSON 响应。 */
    public function expectsJson(): bool
    {
        $accept = strtolower((string) $this->header('Accept', ''));

        return $this->isAjax() || str_contains($accept, 'application/json') || str_contains($accept, '+json');
    }
}
