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
     * 将后台路径转为动态地址（index.php?fp=...），不依赖伪静态。
     *
     * 支持带查询串的输入（如 /admin/categories?saved=1），内部会拆出 path
     * 部分做 urlencode 并将查询串原样拼接，避免 Router 因 %3F/%3D 失配。
     */
    protected function adminUrl(string $path): string
    {
        $parts = parse_url($path);
        $cleanPath = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : $path;

        $result = 'index.php?fp=' . urlencode(ltrim($cleanPath, '/'));

        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $result .= '&' . $parts['query'];
        }

        if (isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '') {
            $result .= '#' . $parts['fragment'];
        }

        return $result;
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
