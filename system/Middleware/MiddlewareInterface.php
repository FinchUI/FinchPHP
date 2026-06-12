<?php

/**
 * Finch\Middleware\MiddlewareInterface - HTTP 中间件约定
 */

declare(strict_types=1);

namespace Finch\Middleware;

use Finch\Core\Request;
use Finch\Core\Response;

interface MiddlewareInterface
{
    /**
     * @param array<string, mixed> $route 当前匹配的路由定义
     * @param list<string> $args 路由声明中的中间件参数，如 role:editor → ['editor']
     */
    public function handle(Request $request, callable $next, array $route = [], array $args = []): Response;
}
