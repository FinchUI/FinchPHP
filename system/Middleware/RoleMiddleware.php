<?php

/**
 * Finch\Middleware\RoleMiddleware - Capability 权限校验
 */

declare(strict_types=1);

namespace Finch\Middleware;

use Finch\App;
use Finch\Core\Request;
use Finch\Core\Response;

final class RoleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $route = [], array $args = []): Response
    {
        $capability = $args[0] ?? (string) ($route['capability'] ?? '');
        if ($capability === '') {
            return $next($request);
        }

        $app = App::getInstance();
        if ($app->gate->allows($app->user, $capability)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return (new Response())->json([
                'code'    => 3100,
                'message' => '权限不足',
                'data'    => null,
            ], 403);
        }

        return (new Response())->html($this->forbiddenPage(), 403);
    }

    private function forbiddenPage(): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>权限不足 - Finch PHP</title></head>'
            . '<body style="font-family:sans-serif;padding:3rem">'
            . '<h1>权限不足</h1><p>当前用户没有访问该资源所需的权限。</p></body></html>';
    }
}
