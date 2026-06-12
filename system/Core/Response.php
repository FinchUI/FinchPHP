<?php

/**
 * Finch\Core\Response - HTTP 响应封装
 *
 * 统一输出 HTML/JSON 与状态码控制（见 PROJECT_PLAN §4.1 目录说明）。
 */

declare(strict_types=1);

namespace Finch\Core;

final class Response
{
    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $headers = [];

    private string $body = '';

    /** 设置 HTTP 状态码 */
    public function setStatus(int $code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    /** 设置响应头 */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /** 获取 HTTP 状态码。 */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** 获取响应头。 */
    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** 获取响应正文。 */
    public function body(): string
    {
        return $this->body;
    }

    /** 设置 HTML 响应体 */
    public function html(string $content, int $status = 200): self
    {
        $this->statusCode = $status;
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
        $this->body = $content;

        return $this;
    }

    /** 设置 JSON 响应体 */
    public function json(mixed $data, int $status = 200): self
    {
        $this->statusCode = $status;
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->body = (string) json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return $this;
    }

    /** 设置重定向 */
    public function redirect(string $url, int $status = 302): self
    {
        $this->statusCode = $status;
        $this->setHeader('Location', $url);
        $this->body = '';

        return $this;
    }

    /** 发送响应到客户端 */
    public function send(): void
    {
        $this->applyDefaultHeaders();

        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }

    private function applyDefaultHeaders(): void
    {
        if (!isset($this->headers['X-Content-Type-Options'])) {
            $this->headers['X-Content-Type-Options'] = 'nosniff';
        }

        if (!isset($this->headers['X-Frame-Options'])) {
            $this->headers['X-Frame-Options'] = 'SAMEORIGIN';
        }

        if (!isset($this->headers['Referrer-Policy'])) {
            $this->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        }

        if (!isset($this->headers['Permissions-Policy'])) {
            $this->headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()';
        }

        if (!isset($this->headers['Content-Security-Policy'])) {
            $this->headers['Content-Security-Policy'] = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'";
        }

        if (!isset($this->headers['Strict-Transport-Security']) && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $this->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $contentType = strtolower((string) ($this->headers['Content-Type'] ?? ''));
        if (str_contains($contentType, 'application/json') && !isset($this->headers['Cache-Control'])) {
            $this->headers['Cache-Control'] = 'no-store, no-cache, must-revalidate';
            $this->headers['Pragma'] = 'no-cache';
        }
    }
}
