<?php

/**
 * Finch\Middleware\CorsMiddleware - API 跨域响应头
 */

declare(strict_types=1);

namespace Finch\Middleware;

use Finch\App;
use Finch\Core\Request;
use Finch\Core\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $route = [], array $args = []): Response
    {
        $origin = $request->header('Origin', '*');
        $allowedOrigin = $this->allowedOrigin($origin);

        if ($request->method() === 'OPTIONS') {
            $response = (new Response())->setStatus(204);
        } else {
            $response = $next($request);
        }

        return $response
            ->setHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN')
            ->setHeader('Access-Control-Allow-Credentials', $allowedOrigin === '*' ? 'false' : 'true')
            ->setHeader('Vary', 'Origin')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->setHeader('X-API-Version', 'v1');
    }

    private function allowedOrigin(string $origin): string
    {
        $app = App::getInstance();
        $configured = isset($app->settings) ? (string) $app->settings->get('api_cors_origins', '') : '';
        if ($configured === '') {
            return '*';
        }

        $allowed = array_map('trim', explode(',', $configured));

        return in_array($origin, $allowed, true) ? $origin : $allowed[0];
    }
}
