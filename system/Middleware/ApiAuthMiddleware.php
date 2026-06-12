<?php

/**
 * Finch\Middleware\ApiAuthMiddleware - API Token 认证
 */

declare(strict_types=1);

namespace Finch\Middleware;

use Finch\Api\ApiResource;
use Finch\App;
use Finch\Core\Request;
use Finch\Core\Response;
use Finch\Service\ApiTokenService;

final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $route = [], array $args = []): Response
    {
        $app = App::getInstance();
        $app->setCurrentApiToken(null);

        if ($app->settings->get('api_enabled', true) !== true) {
            return ApiResource::error('API 已禁用', 2001, 503);
        }

        $token = (new ApiTokenService($app->db))->authenticate($request);
        if ($token === null) {
            return ApiResource::error('未认证或 token 已失效', 3000, 401);
        }

        $app->setCurrentApiToken($token);
        $app->setCurrentUser($token->user());

        return $next($request);
    }
}
