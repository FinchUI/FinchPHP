<?php

/**
 * Finch\Controller\BaseController - 控制器基类
 *
 * 控制器层只协调 Request/Response 与视图渲染，不承载业务事务。
 */

declare(strict_types=1);

namespace Finch\Controller;

use Finch\App;
use Finch\Core\Request;
use Finch\Core\Response;
use Finch\Core\Validation;
use Finch\Validation\Validator;

abstract class BaseController
{
    protected App $app;

    public function __construct(protected readonly Request $request)
    {
        $this->app = App::getInstance();
    }

    protected function html(string $content, int $status = 200): Response
    {
        return (new Response())->html($content, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return (new Response())->json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return (new Response())->redirect($this->adminUrl($url), $status);
    }

    protected function currentUser(): mixed
    {
        return $this->app->user ?? null;
    }

    protected function requireLogin(): ?Response
    {
        if ($this->currentUser() !== null) {
            return null;
        }

        return $this->redirect('/admin/login');
    }

    /** @param array<string, string|list<string>> $rules */
    protected function validate(array $rules): Validator
    {
        return Validation::make($this->request, $rules);
    }

    /**
     * 将后台路径转换为当前路由模式对应的 URL。
     * rewrite 模式：原样返回（如 /admin/posts）
     * active 模式：转为 index.php?fp=admin/posts
     */
    protected function adminUrl(string $path): string
    {
        if ($this->isActiveRequest()) {
            return 'index.php?fp=' . urlencode(ltrim($path, '/'));
        }

        return $path;
    }

    /** 当前请求是否以 active（query string）模式路由 */
    protected function isActiveRequest(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        return str_contains($uri, 'index.php');
    }

    protected function authorize(string $capability): bool
    {
        return $this->app->gate->allows($this->currentUser(), $capability);
    }

    protected function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
