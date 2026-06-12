<?php

/**
 * Finch\Middleware\AuthMiddleware - 登录态校验
 */

declare(strict_types=1);

namespace Finch\Middleware;

use Finch\App;
use Finch\Core\Request;
use Finch\Core\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $route = [], array $args = []): Response
    {
        $app = App::getInstance();
        if ($app->isLoggedIn) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return (new Response())->json([
                'code'    => 3000,
                'message' => '请先登录',
                'data'    => null,
            ], 401);
        }

        $loginUrl = (string) ($route['login'] ?? '/admin/login');

        return (new Response())->redirect($loginUrl);
    }
}
