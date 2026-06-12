<?php

/**
 * Finch\Middleware\ApiAbilityMiddleware - API Token abilities 校验
 */

declare(strict_types=1);

namespace Finch\Middleware;

use Finch\Api\ApiResource;
use Finch\App;
use Finch\Core\Request;
use Finch\Core\Response;
use Finch\Model\ApiToken;

final class ApiAbilityMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $route = [], array $args = []): Response
    {
        $ability = $args[0] ?? (string) ($route['ability'] ?? '');
        if ($ability === '') {
            return $next($request);
        }

        $app = App::getInstance();
        $token = isset($app->apiToken) ? $app->apiToken : null;
        if (!$token instanceof ApiToken) {
            return ApiResource::error('未认证或 token 已失效', 3000, 401);
        }

        if (!$token->allows($ability)) {
            return ApiResource::error('Token 权限不足', 3101, 403);
        }

        return $next($request);
    }
}
